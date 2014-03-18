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

function CvError($error, $extras) {
	Mage::getSingleton('core/session')->addError($error);
	Mage::log('coinvoice: '.$error.' '.$extras);
}

function post_coinvoice(&$coinvoiceReply, &$coinvoice_url) {
	$order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();
	$order = Mage::getModel('sales/order');
	$order->loadByIncrementId($order_id);
	Mage::helper('coinvoice')->debug(__METHOD__, 'order_id: '.$order_id);

	$billingId = $order->getBillingAddress()->getId();
	$address = Mage::getModel('sales/order_address')->load($billingId)->getData();

	// translate magento order into coinvoice invoice
	$invoiceRequest = new CvInvoiceRequest();
	$invoiceRequest->PayerName     = $address['firstname'].' '.$address['lastname'];
	$invoiceRequest->PayerAddress1 = $address['street'];
	$invoiceRequest->PayerAddress2 = '';
	$invoiceRequest->PayerCity     = $address['city'];
	$invoiceRequest->PayerState    = $order->getBillingAddress()->getRegionCode();
	$invoiceRequest->PayerZip      = $address['postcode'];
	$invoiceRequest->PayerCountry  = $address['country_id'];
	$invoiceRequest->PayerEmail    = $address['email'];
	$invoiceRequest->PayerCurrency = 'BTC';
	$invoiceRequest->PriceCurrency = Mage::app()->getStore()->getCurrentCurrencyCode();
	if ($invoiceRequest->PriceCurrency !== 'USD') {
		Mage::helper('coinvoice')->debug(__METHOD__, "Invalid PriceCurrency, only USD supported for now!");
		CvError(_('Internal error(1): currently only USD is supported for PriceCurrency'));
		return false;
	}
	if ($address['telephone'] === "") {
		$invoiceRequest->PayerPhone = '555 555 5555';
	} else {
		$invoiceRequest->PayerPhone = $address['telephone'];
	}

	//line items
	$total = 0.0;
	$itemCount = 0;
	$items = $order->getItemsCollection();
	foreach ($items as $m_item) {
		if ($m_item->getParentItem()) {
			continue;
		}

		$item = new CvItem();
		$item->ItemCode = $m_item->getSku();
		$item->ItemDesc = $m_item->getName();
		$item->ItemQuantity = sprintf("%d", $m_item->getQtyOrdered());
		$item->ItemPricePer = sprintf("%.2f", $m_item->getPrice());
		if ($invoiceRequest->ItemAdd($item, $error) === false) {
			Mage::helper('coinvoice')->debug(__METHOD__, "ItemAdd $error");
			CvError(_('Internal error(2): ').$error);
			return false;
		}
		$total += $item->ItemQuantity * $item->ItemPricePer;
		$itemCount++;
	}
	Mage::helper('coinvoice')->debug(__METHOD__, 'total:   '.$total);

	// shipping
	$shipping = $order->getShippingAmount();
	if ($shipping != 0) {
		$item = new CvItem();
		$item->ItemCode = "";
		$item->ItemDesc = _("Shipping");
		$item->ItemQuantity = "1";
		$item->ItemPricePer = sprintf("%.2f", $shipping);
		if ($invoiceRequest->ItemAdd($item, $error) === false) {
			Mage::helper('coinvoice')->debug(__METHOD__, "ItemAdd $error");
			CvError(_('Internal error(2.1): Please contact the administrator').$error);
			return false;
		}
		$total += $item->ItemQuantity * $item->ItemPricePer;
		$itemCount++;
	}
	Mage::helper('coinvoice')->debug(__METHOD__, 'shipping:   '.$shipping);

	// tax
	$tax = $order->getTaxAmount();
	if ($tax != 0) {
		$item = new CvItem();
		$item->ItemCode = "";
		$item->ItemDesc = _("Sales tax");
		$item->ItemQuantity = "1";
		$item->ItemPricePer = sprintf("%.2f", $tax);
		if ($invoiceRequest->ItemAdd($item, $error) === false) {
			Mage::helper('coinvoice')->debug(__METHOD__, "ItemAdd $error");
			CvError(_('Internal error(2.2): Please contact the administrator').$error);
			return false;
		}
		$total += $item->ItemQuantity * $item->ItemPricePer;
		$itemCount++;
	}
	Mage::helper('coinvoice')->debug(__METHOD__, 'tax:   '.$tax);

	// discounts
	$discount = $order->getDiscountAmount();
	if ($discount != 0) {
		$item = new CvItem();
		$item->ItemCode = "";
		$item->ItemDesc = _("Discount");
		$item->ItemQuantity = "1";
		$item->ItemPricePer = sprintf("%.2f", $discount);
		if ($invoiceRequest->ItemAdd($item, $error) === false) {
			Mage::helper('coinvoice')->debug(__METHOD__, "ItemAdd $error");
			CvError(_('Internal error(2.3): Please contact the administrator').$error);
			return false;
		}
		$total += $item->ItemQuantity * $item->ItemPricePer;
		$itemCount++;
	}
	Mage::helper('coinvoice')->debug(__METHOD__, 'discount: '.$discount);

	// assert order total
	Mage::helper('coinvoice')->debug(__METHOD__, 'calculated total: '.$total);
	Mage::helper('coinvoice')->debug(__METHOD__, 'total           : '.$order->getGrandTotal());
	if (abs($order->getGrandTotal() - $total) > 0.01) {
		Mage::helper('coinvoice')->debug(__METHOD__, "Totals do not match");
		CvError(_('Internal error(2.4): Please contact the administrator'));
		return false;
	}


	// Notifications
	$invoiceRequest->NotificationURL = Mage::getUrl('coinvoice');
	Mage::helper('coinvoice')->debug(__METHOD__, 'out: '.$invoiceRequest->NotificationURL);
	$invoiceRequest->InternalInvoiceId = base64_encode(serialize(
		array(Mage::getSingleton('checkout/session')->getLastRealOrderId())));
//	// create a unique enough key to cause duplicate order collisions
//	//$data = $wc_order->id.$invoiceRequest->PayerName.$invoiceRequest->PayerEmail.$total.$itemCount;
//	//$key = get_site_url();
//	//$invoiceRequest->AlternateInvoiceKey = hash_hmac('sha256', $data, $key, false);

	if ('1' === Mage::getStoreConfig('payment/coinvoice/sandbox')) {
		Mage::helper('coinvoice')->debug(__METHOD__, 'SANDBOX MODE');
		$invoiceRequest->TestInvoice = 'yes';
	}

	if (!$invoiceRequest->Marshal($json, $error)) {
		Mage::helper('coinvoice')->debug(__METHOD__, "Marshal $error");
		CvError(_('Internal error(3): ').$error);
		return false;
	}
	Mage::helper('coinvoice')->debug(__METHOD__, 'out: '.$json);

	// submit invoice to coinvoice
	$coinvoice = new Coinvoice();
	$coinvoice->SetUserAgent("Magento/1.8"); // XXX do something moar bettar
	$coinvoice->SetApiKey(Mage::getStoreConfig('payment/coinvoice/apikey'));

	if ('1' === Mage::getStoreConfig('payment/coinvoice/sandbox')) {
		$coinvoice_url = $coinvoice->GetSandboxHostName();
	} else {
		$coinvoice_url = $coinvoice->GetHostName();
	}

	if (!$coinvoice->Post($json, $reply, $error)) {
		Mage::helper('coinvoice')->debug(__METHOD__, "Post $error");

		// see if we got a better error to show user
		$CvFailure = new CvFailure();
		if ($CvFailure->Unmarshal($reply, $error)) {
			// successfully unmarshaled, use ErrorCode instead
			$error = $CvFailure->ErrorCode;
		}
		CvError(_('Internal error(4): ').$error, $reply);
		return false;
	}
	Mage::helper('coinvoice')->debug(__METHOD__, 'in: '.var_export($reply, true));

	// decode reply from coinvoice
	$coinvoiceReply = new CvInvoiceReply();
	if (!$coinvoiceReply->Unmarshal($reply, $error)) {
		Mage::helper('coinvoice')->debug(__METHOD__, "Unmarshal $error");

		// see if we got a better error to show user
		$CvFailure = new CvFailure();
		if ($CvFailure->Unmarshal($reply, $error)) {
			// successfully unmarshaled, use ErrorCode instead
			$error = $CvFailure->ErrorCode;
		}
		Mage::helper('coinvoice')->debug(__METHOD__, "post_invoice->Unmarshal user error: $error");

		CvError(_('Internal error(5): ').$error, $reply);
		return false;
	}

	if ($coinvoiceReply->Status !== CvInvoiceNotification::InvoiceStatusNew) {
		// TODO do something with other invoice stati
		Mage::helper('coinvoice')->debug(__METHOD__, "Wrong invoice status $error");
		CvError(_('Internal error(6): status = ').$coinvoiceReply->Status);
		return false;
	}

	return true;
}

class Conformal_Coinvoice_PaymentController extends Mage_Core_Controller_Front_Action {
	// The redirect action is triggered when someone places an order
	public function redirectAction() {
		Mage::helper('coinvoice')->debug(__METHOD__, '');
		if (!post_coinvoice($coinvoiceReply, $coinvoice_url)) {
			// error should have been reported
			$this->loadLayout();
			$this->renderLayout();
			return;
		}
		$redirect = $coinvoice_url.'/pay/'.$coinvoiceReply->PaymentLinkId.'/'.base64_encode(Mage::getUrl());
		Mage::helper('coinvoice')->debug(__METHOD__, "POST: $redirect");
		Mage::helper('coinvoice')->debug(__METHOD__, 'return url: '.Mage::getUrl('checkout/onepage/success'));

		$this->_redirectUrl($redirect);
	}
}
