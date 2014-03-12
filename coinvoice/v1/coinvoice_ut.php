<?php
/*
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

include dirname(__FILE__).'/coinvoice.php';

$death = false;

/**
 * Unit Test for coinvoice V1 API.
 */

function fail($msg) {
	echo "$msg";
	if ($GLOBALS['death'] === true) {
		die;
	}
}

function testJsonItem() {
	echo "Testing CvItem\n";

	$item = new CvItem();
	$item->ItemCode      = 'item code';
	$item->ItemDesc      = 'item desc';
	$item->ItemQuantity  = '12';
	$item->ItemPricePer  = '12.22';

	echo "\tCvItem->Marshal() ";
	if (!$item->Marshal($json, $error)) {
		fail("failure: $error\n");
	} else {
		echo "success\n";
	}

	echo "\tCvItem->Validate() negative 1 ";
	$item->ItemQuantity = 'twelve';
	$item->ItemDesc     = null; // check this along the way
	if (!$item->Validate($error)) {
		// test case success
		echo "success\n";
	} else {
		fail("failure: $error\n");
	}

	echo "\tCvItem->Validate() negative 2 ";
	$item->ItemCode     = null; // check this along the way
	$item->ItemQuantity = '12'; // reset
	$item->ItemCode = 0xdeadbeef;
	if (!$item->Validate($error)) {
		// test case success
		echo "success\n";
	} else {
		fail("failure: $error\n");
	}

	echo "\tCvItem->Validate() negative 3 ";
	$item->ItemCode = 'item code'; // reset
	$item->ItemPricePer = null;
	if (!$item->Validate($error)) {
		// test case success
		echo "success\n";
	} else {
		fail("failure: $error\n");
	}
}

function testJsonInvoiceRequest() {
	echo "Testing CvInvoiceRequest\n";

	$invoiceRequest = new CvInvoiceRequest();
	$invoiceRequest->PayerAddress1 = 'Address line 1';
	$invoiceRequest->PayerCity     = 'City';
	$invoiceRequest->PayerCountry  = 'Country';

	$item = new CvItem();
	$item->ItemCode      = 'item code';
	$item->ItemDesc      = 'item desc';
	$item->ItemQuantity  = '12';
	$item->ItemPricePer  = '12.12';

	$item2 = new CvItem();
	$item2->ItemCode      = 'item2 code';
	$item2->ItemDesc      = 'item2 desc';
	$item2->ItemQuantity  = '21';
	$item2->ItemPricePer  = '21.21';

	echo "\tCvInvoiceRequest->ItemAdd() ";
	if ($invoiceRequest->ItemAdd($item, $error) === false ||
	    $invoiceRequest->ItemAdd($item2, $error) === false) {
		fail("failure (could not add elements)\n");
	} else {
		echo "success\n";
	}

	echo "\tCvInvoiceRequest->ItemCount() ";
	if ($invoiceRequest->ItemCount() !== 2) {
		fail("failure (invalid element count)\n");
	} else {
		echo "success\n";
	}

	echo "\tCvInvoiceRequest->Validate() negative 1 ";
	$invoiceRequest->PayerCurrency = 'notUSD';
	if (!$invoiceRequest->Validate($json, $error)) {
		// test case success
		echo "success\n";
	} else {
		fail("failure: (invalid currency)\n");
	}
	$invoiceRequest->PayerCurrency = 'BTC'; // reset

	echo "\tCvInvoiceRequest->Validate() negative 2 ";
	$invoiceRequest->PayerName = null;
	if (!$invoiceRequest->Validate($json, $error)) {
		// test case success
		echo "success\n";
	} else {
		fail("failure: (invalid PayerName)\n");
	}
	$invoiceRequest->PayerName = '';

	echo "\tCvInvoiceRequest->Validate() negative 3 ";
	$invoiceRequest->PayerName = null;
	if (!$invoiceRequest->Validate($json, $error)) {
		// test case success
		echo "success\n";
	} else {
		fail("failure: (invalid PayerName length)\n");
	}

	$invoiceRequest->PayerName = '';
	$invoiceRequest->PayerName = 'Moo McMooer';
	$invoiceRequest->PriceCurrency = 'USD';

	echo "\tCvInvoiceRequest->Marshal() ";
	if (!$invoiceRequest->Marshal($json, $error)) {
		fail("failure: $error\n");
	} else {
		echo "success\n";
	}

	return $invoiceRequest;
}

function testIntl() {
	echo "Testing gettext\n";

	if (!function_exists("gettext")) {
		echo "gettext is not installed, aborting\n";
		die;
	}

	$language = "en_US";
	putenv("LANG=" . $language); 
	setlocale(LC_ALL, $language);

	$domain = "messages";
	bindtextdomain($domain, dirname(__FILE__).'/locale'); 
	bind_textdomain_codeset($domain, 'UTF-8');
	 
	textdomain($domain);

	echo _("HELLO_WORLD");
}

function myJsonPost($url, $headers, $json, &$reply, &$error) {
	echo '(myJsonPost) ';

	if (!function_exists("curl_init")) {
		$error = _('curl is not installed');
		return false;
	}

	$ch = curl_init($url);
	if ($ch === false) {
		$error = curl_error($ch);
		return false;
	}

	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERAGENT, 'coinvoice_ut/1');

	$reply = curl_exec($ch);
	if ($reply === false) {
		$error = curl_error($ch);
		return false;
	}

	$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if (empty($http_status)) {
		$error = _('No HTTP code was returned');
		return false;
	} else if ($http_status !== 200) {
		$error = $http_status;
		return false;
	}

	return true;
}

function testPost($invoiceRequest) {
	echo "Testing POST\n";

	$coinvoice = new Coinvoice();
	$invoiceRequest->TestInvoice = 'yes';
	$coinvoice->SetUserAgent('bogusagent/1.0');
	$coinvoice->SetApiKey(getenv('APIKEY'));
	//$invoiceRequest->NotificationURL = 'http://blog.mydoamin.com/?wc-api=wc_coinvoice';

	$version = $coinvoice->GetVersion();
	echo "\tCoinvoice->GetVersion() $version\n";

	// error case
	echo "\tCoinvoice->Marshal() ";
	if (!$invoiceRequest->Marshal($json, $error)) {
		fail("failure: $error\n");
	} else {
		echo "succes\n";
	}
	echo "\tCoinvoice->Post() default negative ";
	if ($coinvoice->Post($json, $reply, $error)) {
		fail("failure\n");
	} else {
		echo "succes\n";
	}

	// success case
	$invoiceRequest->PayerAddress1 = 'Address 1';
	$invoiceRequest->PayerCity     = 'Bumble Bee';
	$invoiceRequest->PayerState    = 'TX';
	$invoiceRequest->PayerZip      = '12345';
	$invoiceRequest->PayerCountry  = 'US';
	$invoiceRequest->PayerPhone    = '555 555 555';
	$invoiceRequest->PayerEmail    = 'moo@mydomain.dom';
	echo "\tCoinvoice->Marshal() ";
	if (!$invoiceRequest->Marshal($json, $error)) {
		fail("failure: $error\n");
	} else {
		echo "succes\n";
	}

	echo "\tCoinvoice->Post() default ";
	if (!$coinvoice->Post($json, $reply, $error)) {
		fail("failure: $error, $reply\n");
	} else {
		echo "succes\n";
	}

	echo "\tCoinvoice->Unmarshal() default ";
	$invoiceReply = new CvInvoiceReply();
	if (!$invoiceReply->Unmarshal($reply, $error)) {
		fail("failure: $error\n");
	} else {
		echo "succes\n";
	}

	echo "\tCoinvoice->Post() external ";
	$coinvoice->SetPostFunctionName('myJsonPost');
	if (!$coinvoice->post($json, $reply, $error)) {
		fail("failure: $error\n");
	} else {
		echo "succes\n";
	}
}
function testNotify() {
	echo "Testing Notify\n";
	$body = '{"id":"JKVUZXYH","status":"2","alternateInvoiceKey":"a:2:{i:0;i:222;i:1;s:22:\\"wc_order_5303f7b69ad73\\";}"}';
	echo "\tCvInvoiceNotification->Unmarshal() ";
	$coinvoiceNotify = new CvInvoiceNotification();
	if (!$coinvoiceNotify->Unmarshal($body, $error)) {
		fail("failure: $error\n");
	} else {
		echo "succes\n";
	}

	echo "\tCvInvoiceNotification->Unmarshal() negative ";
	$body = 'yep, not json';
	$coinvoiceNotify = new CvInvoiceNotification();
	if (!$coinvoiceNotify->Unmarshal($body, $error)) {
		// success case
		echo "succes\n";
	} else {
		fail("failure: $error\n");
	}
}

function testReply() {
	echo "Testing Reply\n";

	$reply = '{"id":"MQYMVGEG","status":"0","price":"2839.98","priceCurrency":"USD","PaymentLinkId":"TPJPOQHG","totalBtc":"4.53143948","remainingBTC":"4.53143948","ExpirationTime":"1392775997","CurrentTime":"1392775097","BtcQuoteRate":"626.72800000","BtcAddress":"nopechucktesta"}';

	echo "\tCvInvoiceReply->Unmarshal() ";
	$coinvoiceReply = new CvInvoiceReply();
	if (!$coinvoiceReply->Unmarshal($reply, $error)) {
		fail("failure: $error\n");
	} else {
		echo "succes\n";
	}

	echo "\tCvInvoiceReply->Unmarshal() negative ";
	$reply = 'All your base are belong to us!';
	$coinvoiceReply = new CvInvoiceReply();
	if (!$coinvoiceReply->Unmarshal($reply, $error)) {
		// success case
		echo "succes\n";
	} else {
		fail("failure: $error\n");
	}
}

function testFailure() {
	echo "Testing Failure\n";

	$reply = '{"errorCode":4262,"errorText":"Our wallet is currently unavailable. Please try again laterError 4262: Please contact support\\n"}';

	echo "\tCvFailure->Unmarshal() ";
	$CvFailure = new CvFailure();
	if (!$CvFailure->Unmarshal($reply, $error)) {
		fail("failure: $error\n");
	} else {
		echo "succes\n";
	}

	echo "\tCvFailure->Unmarshal() negative ";
	$reply = "not json";
	$CvFailure = new CvFailure();
	if (!$CvFailure->Unmarshal($reply, $error)) {
		echo "succes\n";
	} else {
		fail("failure: $error\n");
	}

	$reply = '{"errorCode":0,"errorText":"You have entered an invalid price. You have entered an invalid price."}';
	echo "\tCvFailure->Unmarshal() 2 ";
	$CvFailure = new CvFailure();
	if (!$CvFailure->Unmarshal($reply, $error)) {
		fail("failure: $error\n");
	} else {
		echo "succes\n";
	}

}

// main
if (count($argv) > 1 && $argv[1] === '-die') {
	$death = true;
}

//testIntl();
testNotify();
testReply();
testFailure();

testJsonItem();
$invoiceRequest = testJsonInvoiceRequest();
testPost($invoiceRequest);
