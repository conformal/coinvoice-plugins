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

$coinvoiceApi=Mage::getModuleDir('', 'Conformal_Coinvoice').'/coinvoice/v1/coinvoice.php';

require_once($coinvoiceApi);
class Conformal_Coinvoice_IndexController extends Mage_Core_Controller_Front_Action {

	// this is the callback
	public function indexAction() {
		Mage::helper('coinvoice')->debug(__METHOD__, "==== callback ====");

		// obtain body
		@ob_clean();
		$body = file_get_contents('php://input');
		Mage::helper('coinvoice')->debug(__METHOD__, 'POST body: '.var_export($body, true));

		// unmarshal $body
		$coinvoiceNotify = new CvInvoiceNotification();
		if (!$coinvoiceNotify->Unmarshal($body, $error)) {
			// shouldn't happen, how to handle?
			Mage::helper('coinvoice')->debug(__METHOD__, 'coinvoiceNotify->Unmarshal '.$error);
			return;
		}

		// find order
		$decoded = base64_decode($coinvoiceNotify->InternalInvoiceId, true);
		if ($decode === false) {
			// shouldn't happen, how to handle?
			Mage::helper('coinvoice')->debug(__METHOD__, 'base64_decode error');
			return;
		}
		$magentoOrder = unserialize($decoded);
		if ($magentoOrder === false) {
			// shouldn't happen, how to handle?
			Mage::helper('coinvoice')->debug(__METHOD__, 'unserialize error');
			return;
		}
		list($orderId, $cartId) = $magentoOrder;

		// load order
		$order = Mage::getModel('sales/order');
		$order->loadByIncrementId($orderId);
		if ($order === FALSE) {
			Mage::helper('coinvoice')->debug(__METHOD__, 'unknown order: '.$orderId);
			return;
		}

		// move order state forward
		switch ($coinvoiceNotify->Status) {
		case CvInvoiceNotification::InvoiceStatusNew:
			// ignore new invoices
			break;
		case CvInvoiceNotification::InvoiceStatusPPaid:
			// partial payment, put order on hold and notify admin
			Mage::helper('coinvoice')->debug(__METHOD__, 'PARTIAL PAID, order: '.$orderId);
			// XXX

			break;
		case CvInvoiceNotification::InvoiceStatusPaid:
			// payment is ready to be mined
			Mage::helper('coinvoice')->debug(__METHOD__, 'PAID (awaiting confirmation), order: '.$orderId.
				' current status: '.$order->getState());
			if ($order->getState() === Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
				break;
			}
			try {
				$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true,
					'Awaiting blockchain confirmation')->save();
			} catch (Exception $e) {
				Mage::logException($e);
			}
			break;
		case CvInvoiceNotification::InvoiceStatusConfirmed:
			// expected number of confirmations have made it into the blockchain
			Mage::helper('coinvoice')->debug(__METHOD__, 'CONFIRMED (all done), order: '.$orderId.
				' current status: '.$order->getState());
			if ($order->getState() === Mage_Sales_Model_Order::STATE_PROCESSING) {
				break;
			}
			try {
				$comment = 'Coinvoice confirmed transaction '.$coinvoiceNotify->Id;
				$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $comment)->save();

				Mage::getSingleton('checkout/session')->unsQuoteId();
				Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure'=>true));
			} catch (Exception $e) {
				Mage::logException($e);
			}

			break;
		case CvInvoiceNotification::InvoiceStatusComplete:
			// merchant paid out, ignore
			break;
		case CvInvoiceNotification::InvoiceStatusInvalid:
			// something bad happened, put order on failed and notify admin
			try {
				$order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true,
					'Order marked invalid by Coinvoice.  '.
					'Administrator action may be required.')->save();
			} catch (Exception $e) {
				Mage::logException($e);
			}
			break;
		case CvInvoiceNotification::InvoiceStatusCancelled:
			// invoice was canceled, mark order failed order and notify admin
			try {
				$order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true,
					'Order canceled by Coinvoice.  '.
					'Administrator action may be required.')->save();
			} catch (Exception $e) {
				Mage::logException($e);
			}
			break;
		default:
			// Figure this out
			Mage::helper('coinvoice')->debug(__METHOD__, 'NOT HANDLED '.$coinvoiceNotify->Status);
			return;
		}
	}
}
