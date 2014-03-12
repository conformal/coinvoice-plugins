<?php
/**
 * @author Conformal Systems LLC.
 * @copyright Copyright (c) 2014 Conformal Systems LLC. <support@conformal.com>
 * @license
 * Copyright (c) Conformal Systems LLC. <support@conformal.com>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

function uc_coinvoice_notifications() {
	@ob_clean();
	$body = file_get_contents('php://input');
	Debug(__METHOD__, 'POST body: '.var_export($body, true));

	// unmarshal $body
	$coinvoiceNotify = new CvInvoiceNotification();
	if (!$coinvoiceNotify->Unmarshal($body, $error)) {
		// shouldn't happen, how to handle?
		Debug(__METHOD__, 'Unmarshal '.$error);
		return;
	}

	// find order
	$decoded = base64_decode($coinvoiceNotify->InternalInvoiceId, true);
	if ($decode === false) {
		// shouldn't happen, how to handle?
		Debug(__METHOD__, 'base64_decode error');
		return;
	}
	$drupalOrder = unserialize($decoded);
	if ($drupalOrder === false) {
		// shouldn't happen, how to handle?
		Debug(__METHOD__, 'unserialize error');
		return;
	}
	list($orderId, $cartId) = $drupalOrder;

	// load order
	$order = uc_order_load($orderId);
	if ($order === FALSE) {
		Debug(__METHOD__, 'unknown order: '.$orderId);
		return;
	}

	// move order state forward
	switch ($coinvoiceNotify->Status) {
	case CvInvoiceNotification::InvoiceStatusNew:
		// ignore new invoices
		break;
	case CvInvoiceNotification::InvoiceStatusPPaid:
		// partial payment, put order on hold and notify admin
		Debug(__METHOD__, 'PARTIAL PAID, order: '.$orderId);
		// XXX

		break;
	case CvInvoiceNotification::InvoiceStatusPaid:
		// payment is ready to be mined
		Debug(__METHOD__, 'PAID (awaiting confirmation), order: '.$orderId.
			' current status: '.$order->order_status);
		if ($order->order_status === 'processing') {
			break;
		}
		uc_order_update_status($orderId, 'processing');
		uc_order_comment_save($orderId, 0, t('Awaiting blockchain confirmation'), 'admin');
		break;
	case CvInvoiceNotification::InvoiceStatusConfirmed:
		// expected number of confirmations have made it into the blockchain
		Debug(__METHOD__, 'CONFIRMED (all done), order: '.$orderId.
			' current status: '.$order->order_status);
		if ($order->order_status === 'payment_received') {
			break;
		}
		$comment = t('Coinvoice confirmed transaction ').$coinvoiceNotify->Id;
		uc_order_update_status($orderId, 'payment_received');
		uc_payment_enter($orderId, 'coinvoice', $order->order_total, $order->uid, NULL, $comment);
		uc_cart_complete_sale($order);
		uc_order_comment_save($orderId, 0, $comment, 'order');
		break;
	case CvInvoiceNotification::InvoiceStatusComplete:
		// merchant paid out, ignore
		break;
	case CvInvoiceNotification::InvoiceStatusInvalid:
		// something bad happened, put order on failed and notify admin
		uc_order_update_status($orderId, 'canceled');
		uc_order_comment_save($orderId, 0, t('Order marked invalid by Coinvoice.  '.
			'Administrator action may be required.'), 'admin', 'canceled', true);
		break;
	case CvInvoiceNotification::InvoiceStatusCancelled:
		// invoice was canceled, mark order failed order and notify admin
		uc_order_update_status($orderId, 'canceled');
		uc_order_comment_save($orderId, 0, t('Order canceled by Coinvoice.  '.
			'Administrator action may be required.'), 'admin', 'canceled', true);
		break;
	default:
		// Figure this out
		Debug(__METHOD__, 'NOT HANDLED '.$coinvoiceNotify->Status);
		return;
	}
}

function uc_coinvoice_complete() {
	Debug(__METHOD__, "order: ".$_SESSION['cart_order']);

	// load order
	$orderId = $_SESSION['cart_order'];
	$order = uc_order_load($orderId);
	if ($order === FALSE) {
		Debug(__METHOD__, 'unknown order: '.$orderId);
		drupal_set_message(t('Something went wrong with this order.  Please contact the store administrator'));
		drupal_goto('cart');
		return;
	}

	if ($order->order_status !== 'payment_received') {
		Debug(__METHOD__, 'order status: '.$order->order_status);
		drupal_set_message(t('Thank you for your order! Payment has not yet been confirmed by Coinvoice.'.
			'  Please check the order status page for updates.'));
	}

	// need to set this so that we get redirected to completion instead of cart
	$_SESSION['do_complete'] = TRUE;

	drupal_goto('cart/checkout/complete');
}
