<?php
/**
 * Coinvoice API v1 documentation.
 *
 * This document deliniates the Coinvoice API and can be considered the official specification.
 *
 * Notes on style:
 * Coinvoice is written entirely in Go and inherent to that it uses camel capitalization.
 * Since PHP does not have a set style it was decided to keep the internal naming convention.
 * By convention names that start with an upper case letter are public and inversely lower case names are private.
 *
 * API will always return booleans for all results unless explicitly mentioned.
 * We want to safely be able to check results using the bang (!).
 * Inherent to this design decision the resulting human readable error is always passed by reference as the last parameter to a function call.
 *
 * All math is performed using multi-precision rational numbers and therefore all numbers are passed around as strings.
 *
 * @author Conformal Systems LLC.
 * @copyright Copyright (c) 2014 Conformal Systems LLC. <support@conformal.com>
 * @license
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.<br>
 * <br>
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

/**
 * The Coinvoice class is used as a context for communication with coinvoice.com.
 */
class Coinvoice {
	/**
	 * Create invoice POST URL.
	 */
	const URLCREATE = '/api/v1/create_invoice';

	/**
	 * Query invoice POST URL.
	 */
	const URLQUERY = '/api/v1/query_invoice';

	/**
	 * Runtime API version.
	 */
	const VERSION = 'v1';

	/**
	 * @access private
	 * @var string HTTP headers basic authorization.  This is base64 encoded in the HTTP headers.
	 */
	private $auth;

	/**
	 * @access private
	 * @var string Host name URL.
	 */
	private $host = 'https://coinvoice.com';

	/**
	 * @access private
	 * @var string Callback function name that performs HTTP POST.
	 */
	private $post;

	/**
	 * @access private
	 * @var string Sandbox host name URL.
	 */
	private $sandbox = 'https://sandbox.coinvoice.com';

	/**
	 * @access private
	 * @var string user-agent used in HTTP POST.
	 */
	private $userAgent = 'Coinvoice/v1';

	/**
	 * Generate URL based on TestInvoice.
	 *
	 * If TestInvoice is set to 'yes' than the returned URL is pointing to the sandbox.
	 *
	 * @access private
	 * @param string $json valid JSON RPC.  This is used to determine if this is a test invoice.
	 * @return string coinvoice or sandbox URL.
	 */
	private function getUrl($json) {
		$a = json_decode($json, true);
		if ($a !== false && isset($a['TestInvoice'])) {
			return $url = $this->sandbox.self::URLCREATE;
		}
		return $url = $this->host.self::URLCREATE;
	}

	private function createHeader($json, &$headers, &$secure) {
		$url = $this->getUrl($json);

		// create HTTP headers Host field based on the URL
		$url_array = parse_url($url);
		if ($url_array === false || !isset($url_array['host'])) {
			$error = _('could not parse url');
			return false;
		}
		$host = $url_array['host'];
		// set port to 443, by default, if scheme is https
		$secure = false;
		if (isset($url_array['scheme']) && $url_array['scheme'] === 'https') {
			$port = '443';
			$secure = true;
		}
		// override port if needed
		if (isset($url_array['port'])) {
			$port = $url_array['port'];
		}
		if (isset($port)) {
			$host = $host.':'.$port;
		}

		$headers = array(
			'Authorization: Basic '.base64_encode($this->auth),
			'Host: '.$host,
			'Accept: application/json',
			'Content-Type: application/json',
			'Content-Length: '.strlen($json),
		);

		return true;
	}

	/**
	 * Default HTTP POST to coinvoice.com that uses curl.
	 *
	 * @param string $json valid JSON RPC call.
	 * @param string $reply passed by reference, HTTP JSON reply if successful.
	 * @param string $error passed by reference, human readable error if not successful.
	 * When the HTTP status code is not 200/OK return the HTTP code in $error.
	 * The reply may or may not contain a human readable string that can be used for debugging.
	 *
	 * @return boolean true if successful or false if unsuccessful.
	 */
	private function defaultJsonPost($json, &$reply, &$error) {
		if (!function_exists("curl_init")) {
			$error = _('curl_init is not installed');
			return false;
		}

		$url = $this->getUrl($json);
		$ch = curl_init($url);
		if ($ch === false) {
			$error = curl_error($ch);
			return false;
		}

		if (!$this->createHeader($json, $headers, $secure)) {
			$error = _('could not create HTTP headers');
			return false;
		}

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);

		if ($secure === true) {
			// force these even though they are defaults
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		}

		// debug, remove later
		$debug_header = false;
		if ($debug_header === true) {
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		}

		$reply = curl_exec($ch);
		if ($reply === false) {
			$error = curl_error($ch);
			return false;
		}

		if ($debug_header) {
			$headerSent = curl_getinfo($ch, CURLINFO_HEADER_OUT);
			echo $headerSent;
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

	/**
	 * Get the POST host name.
	 *
	 * @param void
	 *
	 * @return string host name.
	 */
	public function GetHostName() {
		return $this->host;
	}

	/**
	 * Get the sandbox host name.
	 *
	 * @param void
	 *
	 * @return string sandbox host name.
	 */
	public function GetSandboxHostName() {
		return $this->sandbox;
	}

	/**
	 * Obtain runtime API version that is being used.
	 *
	 * @return string containing Coinvoice API version.
	 */
	public function GetVersion() {
		return self::VERSION;
	}

	/**
	 * Perform HTTP POST to coinvoice.com.
	 *
	 * @param string $json valid JSON RPC call.
	 * @param string $reply passed by reference, HTTP JSON reply if successful.
	 * @param string $error passed by reference, human readable error if not successful.
	 *
	 * @return boolean true if successful or false if unsuccessful.
	 */
	public function Post($json, &$reply, &$error) {
		if (is_null($this->post)) {
			return $this->defaultJsonPost($json, $reply, $error);
		}

		if (!$this->createHeader($json, $headers, $secure)) {
			return false;
		}

		$url = $this->getUrl($json);
		// We can not use call_user_func here since we'd need &$reply
		// and &$error in order for the values to propagate and
		// php >= 5.4 bombs on that syntax regardless if the line is
		// executed.
		$fn = $this->post;
		return $fn($url, $headers, $json, $reply, $error);
	}

	/**
	 * Set authorization for coinvoice.
	 *
	 * This value can be obtained from the coinvoice site.
	 *
	 * @param string $auth this value is base64 encoded and added to the HTTP headers as the basic authorization.
	 *
	 * @return void
	 */
	public function SetApiKey($auth) {
		$this->auth = $auth;
	}

	/**
	 * Set host name to connect to.
	 *
	 * <strong>This is a debug option, do not use!</strong>
	 *
	 * @param string $host name that the post will connect to to perform POSTs.
	 *
	 * @return void
	 */
	public function SetHost($host) {
		$this->host = $host;
	}

	/**
	 * Set user agent to use during HTTP POST.
	 *
	 * @param string $userAgent
	 *
	 * @return void
	 */
	public function SetUserAgent($userAgent) {
		$this->userAgent = $userAgent;
	}

	/**
	 * Set external function to call when doing an HTTP POST.
	 *
	 * WARNING:
	 * The function name <strong>MUST</strong> be passed as a string.
	 *
	 * The external function must return true or false and has the following signature:
	 * <pre>function myJsonPost($url, $json, &$reply, &$error) : boolean
	 *
	 * string &#09; $url &#09;&#09; is filled in by the Coinvoice object.
	 * string &#09; $headers &#09;&#09; are the HTTP headers that are filled in by the Coinvoice object.  The headers are in CURL format and might have to be transformed for other uses.  An example of this is done in the drupal 6 ubercart plugin.
	 * string &#09; $json &#09;&#09; is the JSON RPC command that is going to be POST.
	 * string &#09; &$reply &#09; is sent in by reference and on success will contain the Coinvoice reply to the RPC command.
	 * string &#09; &$error &#09; is sent in by reference and on failure contains a human readable error.
	 *</pre>
	 * Here is an example of an external POST function:
	 * <pre>
	 * function myJsonPost($url, $headers, $json, &$reply, &$error) {
	 * 	echo '(myJsonPost) ';
	 *
	 * 	if (!function_exists("curl_init")) {
	 * 		$error = _('curl is not installed');
	 * 		return false;
	 * 	}
	 *
	 * 	$ch = curl_init($url);
	 * 	if ($ch === false) {
	 * 		$error = curl_error($ch);
	 * 		return false;
	 * 	}
	 *
	 * 	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	 * 	curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
	 * 	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	 * 	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	 * 	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	 * 	curl_setopt($ch, CURLOPT_USERAGENT, 'coinvoice_ut/1');
	 *
	 * 	$reply = curl_exec($ch);
	 * 	if ($reply === false) {
	 * 		$error = curl_error($ch);
	 * 		return false;
	 * 	}
	 *
	 * 	$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	 * 	curl_close($ch);
	 *
	 * 	if (empty($http_status)) {
	 * 		$error = _('No HTTP code was returned');
	 * 		return false;
	 * 	} else if ($http_status !== 200) {
	 * 		$error = $http_status;
	 * 		return false;
	 * 	}
	 *
	 * 	return true;
	 * }
	 * </pre>
	 *
	 * @param string $funcName External function name.
	 *
	 * @return void
	 */
	public function SetPostFunctionName($funcName) {
		$this->post = $funcName;
	}
}

/**
 * The CvFailure class is used to parse an HTTP body that contains additional error information.
 *
 * This class is used to decode the error JSON and provide human readable hints.
 */
class CvFailure {
	/**
	 * @access public
	 * @var string ErrorCode is the error that a site should return to the user, if needed.
	 */
	public $ErrorCode;

	/**
	 * @access public
	 * @var string ErrorText is a human readable explanation of the error.
	 * This value is meant for developers and should not be relied upon.
	 * It simply provides some hints.
	 */
	public $ErrorText;

	/**
	 * The Unmarshal function generates fills out the object fields based on the JSON it is sent.
	 *
	 * Note that the properties are pre defined and the JSON entries that are sent are used to make the translation.
	 * This means that the reply must match EXACTLY what this object expects.
	 *
	 * @param string $json passed by reference, JSON RPC call if successful.
	 * @param string $error passed by reference, human readable error if not successful.
	 *
	 * @return boolean true if successful or false if unsuccessful.
	 */
	public function Unmarshal($json, &$error) {
		$rv = json_decode($json, true);
		if ($rv === false || $rv === null | $rv === "") {
			$error = json_last_error();
			if ($error === 0) {
				$error = _("json reply empty or cannot be decoded");
			}
			return false;
		}

		// walk through all json fields and map the to property names
		// this requires very strict json flowing back and forth
		foreach($rv as $key => $val) {
			$property = ucfirst($key);
			if(property_exists(__CLASS__, $property)) {
				$this->$property = $val;
			} else {
				$error = "property '$property' does not exist.";
					return false;
			}
		}

		/**
		 * @todo add more validaton to fields
		 */

		return true;
	}
}

/**
 * The CvInvoiceNotification class is POSTed by coinvoice.com to the earlier specified NotificationURL.
 *
 * This class is used to decode the notification and provide state transition hints.
 */
class CvInvoiceNotification {
	/**
	 * Newly created invoice.
	 */
	const InvoiceStatusNew = '0';

	/**
	 * Customer partial payment received.
	 */
        const InvoiceStatusPPaid = '1';

	/**
	 * Customer payment received.
	 */
        const InvoiceStatusPaid = '2';

	/**
	 * Customer payment confirmed.
	 */
        const InvoiceStatusConfirmed = '3';

	/**
	 * Complete, merchant paid.
	 */
        const InvoiceStatusComplete = '4';

	/**
	 * Unexpected error.
	 */
        const InvoiceStatusInvalid = '5';

	/**
	 * Cancelled invoice.
	 */
        const InvoiceStatusCancelled = '6';

	/**
	 * @access public
	 * @var string invoice ID of the newly created invoice.
	 */
	public $Id;

	/**
	 * @access public
	 * @var string status of the invoice.
	 */
	public $Status;

	/**
	 * @access public
	 * @var string Internal, to the caller, internal order id.
	 */
	public $InternalInvoiceId;

	/**
	 * @access public
	 * @var string returns the value that was specified in the original request.
	 * Used to cross reference orders and to prevent double POSTs.
	 * This value must be unique for the user.
	 */
	public $AlternateInvoiceKey;

	/**
	 * The Unmarshal function generates fills out the object fields based on the JSON it is sent.
	 *
	 * Note that the properties are pre defined and the JSON entries that are sent are used to make the translation.
	 * This means that the reply must match EXACTLY what this object expects.
	 *
	 * @param string $json passed by reference, JSON RPC call if successful.
	 * @param string $error passed by reference, human readable error if not successful.
	 *
	 * @return boolean true if successful or false if unsuccessful.
	 */
	public function Unmarshal($json, &$error) {
		$rv = json_decode($json, true);
		if ($rv === false || $rv === null | $rv === "") {
			$error = json_last_error();
			if ($error === 0) {
				$error = _("json reply empty or cannot be decoded");
			}
			return false;
		}

		// walk through all json fields and map the to property names
		// this requires very strict json flowing back and forth
		foreach($rv as $key => $val) {
			$property = ucfirst($key);
			if(property_exists(__CLASS__, $property)) {
				$this->$property = $val;
			} else {
				$error = "property '$property' does not exist.";
					return false;
			}
		}

		/**
		 * @todo add more validaton to fields
		 */

		return true;
	}
}

/**
 * The CvInvoiceReply class is returned by coinvoice.com once an invoice has been POSTed.
 *
 * This class is used to decode the reply and provide state transition hints.
 */
class CvInvoiceReply {
	/**
	 * @access public
	 * @var string invoice ID of the newly created invoice.
	 */
	public $Id;

	/**
	 * @access public
	 * @var string address to pay BTC to.
	 */
	public $BtcAddress;

	/**
	 * @access public
	 * @var string quoted BTC exchange rate.
	 */
	public $BtcQuoteRate;

	/**
	 * @access public
	 * @var string Status of the invoice creation.
	 * Possible options are: "New" "Reviewed" and "Complete".
	 */
	public $Status;

	/**
	 * @access public
	 * @var string total price od the invoice.
	 */
	public $Price;

	/**
	 * @access public
	 * @var string currency used for the price.
	 */
	public $PriceCurrency;

	/**
	 * @access public
	 * @var string remaining balance in $PriceCurrency.
	 */
	public $PriceRemaining;

	/**
	 * @access public
	 * @var string contains the token that is used to redirect to the coinvoice payment gateway.
	 */
	public $PaymentLinkId;

	/**
	 * @access public
	 * @var string total amount of bitcoin that need to be paid before quote expires.
	 */
	public $TotalBtc;

	/**
	 * @access public
	 * @var string unpaid Bitcoin balance.
	 */
	public $RemainingBTC;

	/**
	 * @access public
	 * @var string time when the spot exchange rate expires.
	 */
	public $ExpirationTime;

	/**
	 * @access public
	 * @var string time when invoice was entered into the coinvoice system.
	 */
	public $CurrentTime;

	/**
	 * The Unmarshal function generates fills out the object fields based on the JSON it is sent.
	 *
	 * Note that the properties are pre defined and the JSON entries that are sent are used to make the translation.
	 * This means that the reply must match EXACTLY what this object expects.
	 *
	 * @param string $json passed by reference, JSON RPC call if successful.
	 * @param string $error passed by reference, human readable error if not successful.
	 *
	 * @return boolean true if successful or false if unsuccessful.
	 */
	public function Unmarshal($json, &$error) {
		$rv = json_decode($json, true);
		if ($rv === false || $rv === null | $rv === "") {
			$error = json_last_error();
			if ($error === 0) {
				$error = _("json reply empty or cannot be decoded");
			}
			return false;
		}

		// walk through all json fields and map the to property names
		// this requires very strict json flowing back and forth
		foreach($rv as $key => $val) {
			$property = ucfirst($key);
			if(property_exists(__CLASS__, $property)) {
				$this->$property = $val;
			} else {
				$error = "property '$property' does not exist.";
					return false;
			}
		}

		/**
		 * @todo add more validaton to fields
		 */

		return true;
	}
}

/**
 * The CvInvoiceRequest class is used to describe a coinvoice incoice.
 */
class CvInvoiceRequest {
	/**
	 * @access public
	 * @var string PriceCurrency currency that the customer will quoted.
	 * Currently USD and BTC is supported. This field is required.
	 */
	public $PriceCurrency;

	/**
	 * @access public
	 * @var string PayerCurrency currency that the user pays with.
	 * Currently USD and BTC is supported. This field is required.
	 */
	public $PayerCurrency;

	/**
	 * @access public
	 * @var string Internal, to the caller, internal order id.  This field is optional.
	 */
	public $InternalInvoiceId;

	/**
	 *
	 * This field should be accessed using the Item* methods of the CvInvoiceRequest object.
	 *
	 * @access public
	 * @var CvItem[] Array that represents all line items on this invoice. This field is required.
	 */
	public $Items = array();

	/**
	 * @access public
	 * @var string that is unique for the caller to be returned in the notifications.
	 * Used to cross reference orders and to prevent double POSTs.
	 */
	public $AlternateInvoiceKey;

	/**
	 * Notification callback URL.
	 *
	 * When an invoice has been processed by coinvoice this is the URL that it'll connect on and POST the results.
	 * For example, "https://myawesomesite.com/cgi-bin/callback"
	 *
	 * @access public
	 * @var string Callback URL.  This field is optional but recommended.
	 */
	public $NotificationURL;

	/**
	 * @access public
	 * @var string Payer (customer) name. This field is required.
	 */
	public $PayerName;

	/**
	 * @access public
	 * @var string Payer (customer) addres line 1. This field is required.
	 */
	public $PayerAddress1;

	/**
	 * @access public
	 * @var string Payer (customer) address line 2. This field is optional.
	 */
	public $PayerAddress2;

	/**
	 * @access public
	 * @var string Payer (customer) city. This field is required.
	 */
	public $PayerCity;

	/**
	 * @access public
	 * @var string Payer (customer) state. This field is optional.
	 */
	public $PayerState;

	/**
	 * @access public
	 * @var string Payer (customer) zip or postal code. This field is optional but recommended.
	 */
	public $PayerZip;

	/**
	 * @access public
	 * @var string Payer (customer) country. This field is required.
	 */
	public $PayerCountry;

	/**
	 * @access public
	 * @var string Payer (customer) email address. This field is optional but recommended.
	 */
	public $PayerEmail;

	/**
	 * @access public
	 * @var string Payer (customer) phone number. This field is optional but recommended.
	 */
	public $PayerPhone;

	/**
	 * @access public
	 * @var string Internal, to the caller, purchase order id.  This field is optional.
	 */
	public $PurchaseOrderID;

	/**
	 * @access public
	 * @var string enable sandbox mode.  Set to any non zero length string to enable. This field is optional.
	 */
	public $TestInvoice;

	/**
	 * @access public
	 * @var string Number of required block confirmations.  This field is required.
	 */
	public $TransactionSpeed = '6';

	/**
	 * Add CvItem element to the item list array.
	 *
	 * @param CvItem $item to append to line item array.
	 * @param string $error passed by reference, human readable error if not successful.
	 *
	 * @return boolean true if successful or false if unsuccessful.
	 */
	public function ItemAdd($item, &$error) {
		if ($item->Validate($error) === false) {
			return false;
		}

		$this->Items[] = $item;
		return true;
	}
	/**
	 * Return number of line items.
	 *
	 * @return int number of line items.
	 */
	public function ItemCount() {
		if ($this->Items === null || empty($this->Items)) {
			return 0;
		}
		return count($this->Items);
	}

	/**
	 * The Marshal function generates a valid JSON string that can be sent to the backend.
	 *
	 * @param string $json passed by reference, JSON RPC call if successful.
	 * @param string $error passed by reference, human readable error if not successful.
	 *
	 * @return boolean true if successful or false if unsuccessful.
	 */
	public function Marshal(&$json, &$error) {
		if (!$this->Validate($error)) {
			return false;
		}

		$json = json_encode(get_object_vars($this));
		if ($json === false) {
			$error = json_last_error();
			return false;
		}
		return true;
	}

	/**
	 * The Validate function ensures that all fields are of the correct type.
	 *
	 * @param string $error passed by reference, human readable error if not successful.
	 *
	 * @return boolean true if successful or false if unsuccessful.
	 */
	public function Validate(&$error) {
		if (is_null($this->PayerCurrency) || !is_string($this->PayerCurrency) ||
		    !($this->PayerCurrency === 'USD' || $this->PayerCurrency === 'BTC')) {
			$error = _("PayerCurrency is required and must be a string containing 'USD' or 'BTC'");
			return false;
		}
		if (is_null($this->PriceCurrency) || !is_string($this->PriceCurrency) ||
		    !($this->PriceCurrency === 'USD' || $this->PriceCurrency === 'BTC')) {
			$error = _("PriceCurrency is required and must be a string containing 'USD' or 'BTC'");
			return false;
		}
		if (is_null($this->Items) || !is_array($this->Items) || $this->ItemCount() === 0) {
			$error = _("Items is required and must be an array of CvItem");
			return false;
		}
		if (!is_null($this->AlternateInvoiceKey) && !is_string($this->AlternateInvoiceKey)) {
			$error = _("AlternateInvoiceKey is optional and must be a string");
			return false;
		}
		if (is_null($this->PayerName) || !is_string($this->PayerName)) {
			$error = _("PayerName is required and must be a string");
			return false;
		}
		if (is_null($this->PayerAddress1) || !is_string($this->PayerAddress1)) {
			$error = _("PayerAddress1 is required and must be a string");
			return false;
		}
		if (!is_null($this->PayerAddress2) && !is_string($this->PayerAddress2)) {
			$error = _("PayerAddress2 is optional and must be a string");
			return false;
		}
		if (is_null($this->PayerCity) || !is_string($this->PayerCity)) {
			$error = _("PayerCity is required and must be a string");
			return false;
		}
		if (!is_null($this->PayerState) && !is_string($this->PayerState)) {
			$error = _("PayerState is optional and must be a string");
			return false;
		}
		if (!is_null($this->PayerZip) && !is_string($this->PayerZip)) {
			$error = _("PayerZip is optional and must be a string");
			return false;
		}
		if (is_null($this->PayerCountry) || !is_string($this->PayerCountry)) {
			$error = _("PayerCountry is required and must be a string");
			return false;
		}
		if (!is_null($this->PayerEmail) && !is_string($this->PayerEmail)) {
			$error = _("PayerEmail is optional and must be a string");
			return false;
		}
		if (!is_null($this->PayerPhone) && !is_string($this->PayerPhone)) {
			$error = _("PayerPhone is optional and must be a string");
			return false;
		}
		if (!is_null($this->PurchaseOrderID) && !is_string($this->PurchaseOrderID)) {
			$error = _("PurchaseOrderID is optional and must be a string");
			return false;
		}
		if (!is_null($this->InternalInvoiceId) && !is_string($this->InternalInvoiceId)) {
			$error = _("InternalInvoiceId is optional and must be a string");
			return false;
		}
		if (!is_null($this->NotificationURL) && !is_string($this->NotificationURL)) {
			$error = _("NotificationURL is optional and must be a string");
			return false;
		}
		if (!is_null($this->TestInvoice) && !is_string($this->TestInvoice)) {
			$error = _("TestInvoice is optional and must be a string");
			return false;
		}
		if (is_null($this->TransactionSpeed) || !is_string($this->TransactionSpeed)) {
			$error = _("TransactionSpeed is required and must be a string");
			return false;
		}
		return true;
	}
}

/**
 * The CvItem class is used to describe individual line items on an invoice.
 *
 * This class is used to create valid JSON line items that coinvoice.com can decode.
 */
class CvItem {
	/**
	 * @access public
	 * @var string Internal, to the caller, item code. This field is optional.
	 */
	public $ItemCode;

	/**
	 * @access public
	 * @var string Internal, to the caller, item code. This field is optional.
	 */
	public $ItemDesc;

	/**
	 * @access public
	 * @var string Price per item. This field is required.
	 */
	public $ItemPricePer;

	/**
	 * @access public
	 * @var string Number of items. This field is required.
	 */
	public $ItemQuantity;

	/**
	 * The Marshal function generates a valid JSON string that can be sent to the backend.
	 *
	 * @param string $json passed by reference, JSON RPC call if successful.
	 * @param string $error passed by reference, human readable error if not successful.
	 *
	 * @return boolean true if successful or false if unsuccessful.
	 */
	public function Marshal(&$json, &$error) {
		if (!$this->Validate($error)) {
			return false;
		}

		$json = json_encode(get_object_vars($this));
		if ($json === false) {
			$error = json_last_error();
			return false;
		}
		return true;
	}

	/**
	 * The Validate function ensures that all fields are of the correct type.
	 *
	 * @param string $error passed by reference, human readable error if not successful.
	 *
	 * @return boolean true if successful or false if unsuccessful.
	 */
	public function Validate(&$error) {
		if (!is_null($this->ItemCode) && !is_string($this->ItemCode)) {
			$error = _("ItemCode is optional and must be a string");
			return false;
		}
		if (!is_null($this->ItemDesc) && !is_string($this->ItemDesc)) {
			$error = _("ItemDesc is optional and must be a string");
			return false;
		}
		if (is_null($this->ItemQuantity) ||
		    !is_string($this->ItemQuantity) ||
		    !is_numeric($this->ItemQuantity)) {
			$error = _("ItemQuantity is required and must be a string and numeric");
			return false;
		}
		if (is_null($this->ItemPricePer) ||
		    !is_string($this->ItemPricePer) ||
		    !is_numeric($this->ItemPricePer)) {
			$error = _("ItemPricePer is required and must be a string and numeric");
			return false;
		}
		return true;
	}
}
