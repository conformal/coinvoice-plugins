<?php
/*
Plugin Name: WooCommerce Coinvoice payment gateway
Plugin URI: http://www.coinvoice.com
Description: Coinvoice payment gateway for woocommerce
Version: 0.1
Author: Conformal Systems LLC.
Author URI: http://www.conformal.com
 */

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

add_action('plugins_loaded', 'woocommerce_coinvoice_init', 0);

/**
 * Return a user-agent string that is added to the HTTP headers.
 *
 * @return string
 */
function CvUserAgent() {
	return 'WooCommerce/'.WC_VERSION;
}

/**
 * Convert between HTTP headers and what WP expects.
 *
 * WordPress's wp_remote_post expects a named array however, the Coinvoice API returns a numbered array.
 * This functions simply parses thenumbered array and converts it to a named array.
 *
 * @param string $headers coinvoice API header array.
 * @param string $headersOut passed by reference, WordPress compatible array.
 * @param string $error passed by reference, human readable error if not successful.
 * @return boolean true if successful or false if unsuccessful.
 */
function CvWpizeHttpHeaders($headers, &$headersOut, &$error) {
	foreach ($headers as $value) {
		$c = strpos($value, ':');
		if ($c === false) {
			$error = _('could not convert HTTP headers');
			return false;
		}
		$key = substr($value, 0, $c);
		if ($key === false) {
			$error = _('could not convert HTTP headers, invalid key');
			return false;
		}
		$val = substr($value, $c + 1);
		if ($val === false) {
			$error = _('could not convert HTTP headers, invalid value');
			return false;
		}
		$headersOut["$key"] = trim($val);
	}

	return true;
}

/**
 * POST to coinvoice.com.
 *
 * Don't rely on the default POST function since it relies on cURL.
 * Use the WordPress provided wp_remote_post function instead.
 *
 * @param string $url coinvoice API provided URL.  This contains the POST URL.
 * @param string $headers coinvoice API provided HTTP headers array.
 * @param string $json coinvoice API provided JSON messages.  This is the HTTP body.
 * @param string $reply passed by reference and containsi, if successful,  the HTTP body of the reply.
 * @param string $error passed by reference, human readable error if not successful.
 * @return boolean true if successful or false if unsuccessful.
 */
function CvPost($url, $headers, $json, &$reply, &$error) {
	if (!CvWpizeHttpHeaders($headers, $wpHeaders, $error)) {
		return false;
	}

	$args = array(
		'method'=>'POST',
		'headers'=>$wpHeaders,
		'body'=>$json,
		'sslverify'=>true,
		'timeout'=>20,
		'user-agent'=>CvUserAgent(),
	);
	$httpReply = wp_remote_post($url, $args);
	if (is_wp_error($httpReply)) {
		$error = $httpReply->get_error_message();
		return false;
	}
	$reply = $httpReply['body'];
	return true;
}

/**
 * Display an error box on a WooCommerce page.
 *
 * This function determines what WooCommerce version is running on and calls the appropriate method.
 *
 * @param string $error is the message that will be shown to the user.
 * @return void
 */
function CvError($error) {
	if (function_exists('wc_add_notice')) {
		// wordpress >= 2.1
		wc_add_notice($error, 'error');
	} else {
		// wordpress < 2.1
		global $woocommerce;
		$woocommerce->add_error($error);
	}
}

/**
 * This function gets called by WooCommerce when an instantiation of coinvoice is required.
 *
 * @param void
 * @return void
 */
function woocommerce_coinvoice_init() {
	if (!class_exists('WC_Payment_Gateway'))
		return;

	// make the coinvoice API available
	require (dirname(__FILE__).'/coinvoice/v1/coinvoice.php');

	/**
	 * The WC_coinvoice class contains all required functionality to proxy WooCommerce payments into coinvoice.
	 */
	class WC_coinvoice extends WC_Payment_Gateway {
		/**
		 * QR code script handle.
		 */
		const qrcode = 'coinvoice_qrcode';

		/**
		 * Timer script handle.
		 */
		const timer = 'coinvoice_timer';

		/**
		 * Coinvoice style handle.
		 */
		const styleCoinvoice = 'coinvoice_style';

		/**
		 * @access private
		 * @var object used for logging.  This is only used when debug is enabled.
		 */

		private $log;

		/**
		 * Log a debug message.
		 *
		 * This is only true when debugging is enabled.
		 * If debugs is not enabled the function returns.
		 *
		 * @access private
		 * @param string $prefix is what is printed first.  By convention __METHOD__ is used.
		 * @param string $msg actual message that is appended to the log.
		 * @return void
		 */
		private function debug($prefix, $msg) {
			if ($this->debug === "yes") {
				$this->log->add('coinvoice', $prefix.': '.$msg);
			}
		}

		/**
		 * Load scripts and style.
		 *
		 * This function is triggered when WooCommerce loads pages that require coinvoice JS and CSS.
		 *
		 * @access private
		 * @param void
		 * @return void
		 */
		public function woocommerce_coinvoice_scripts() {
			wp_enqueue_script(self::qrcode);
			wp_enqueue_script(self::timer);
			wp_enqueue_style(self::styleCoinvoice);
		}

		/**
		 * Notification callback.
		 *
		 * This function gets called when coinvoice posts a notification.
		 * In order for this callback to be triggered the NotificationURL in the invoice request must be filled in.
		 *
		 * This function also changes the order status based on the received message.
		 * Normal statusses are handled silently however, when something is not normal the site administrator
		 * is sent an email for notification.
		 *
		 * The normal order flow is to move from InvoiceStatusNew to InvoiceStatusPaid and finally to
		 * InvoiceStatusConfirmed.
		 * See the coinvoice API documentation for more information.
		 *
		 * @todo make errors email the administrator as well instead of just returning
		 * @access private
		 * @param void
		 * @return void
		 */
		public function coinvoice_callback() {
			// obtain body
			@ob_clean();
			$body = file_get_contents('php://input');
			$this->debug(__METHOD__, 'POST body: '.var_export($body, true));

			// unmarshal $body
			$coinvoiceNotify = new CvInvoiceNotification();
			if (!$coinvoiceNotify->Unmarshal($body, $error)) {
				// shouldn't happen, how to handle?
				$this->debug(__METHOD__, 'coinvoiceNotify->Unmarshal '.$error);
				return;
			}

			// find order
			$decoded = base64_decode($coinvoiceNotify->InternalInvoiceId, true);
			if ($decode === false) {
				// shouldn't happen, how to handle?
				$this->debug(__METHOD__, 'base64_decode error');
				return;
			}
			$wcOrder = unserialize($decoded);
			if ($wcOrder === false) {
				// shouldn't happen, how to handle?
				$this->debug(__METHOD__, 'unserialize error');
				return;
			}
			list($orderId, $orderKey) = $wcOrder;
			$order = new WC_Order($orderId);
			if (!isset($order->id)) {
				// orderId invalid, try alternate find
				$orderId = wc_get_order_id_by_order_key($orderKey);
				$order = new WC_Order($orderId);
			}
			if ($order->order_key !== $orderKey) {
				// shouldn't happen, how to handle?
				$this->debug(__METHOD__, 'invalid order key');
				return;
			}

			// move order state forward
			switch ($coinvoiceNotify->Status) {
			case CvInvoiceNotification::InvoiceStatusNew:
				// ignore new invoices
				break;
			case CvInvoiceNotification::InvoiceStatusPPaid:
				// partial payment, put order on hold and notify admin
				// TODO: add amounts to email
				$this->debug(__METHOD__, 'PARTIAL PAID, order: '.$order->id);
				$order->update_status('on-hold', __('Partial paid order, '.
					'administrator action required', 'woocommerce'));
				$mailer = WC()->mailer();
				$message = $mailer->wrap_message(__('Partial payment received', 'woocommerce'),
					sprintf(__('Order %s has been marked on-hold due to partial payment.',
					'woocommerce'), $order->get_order_number()));
				$mailer->send(get_option('admin_email'), sprintf(__('Partial payment received for order %s',
					'woocommerce'), $order->get_order_number()), $message);
				break;
			case CvInvoiceNotification::InvoiceStatusPaid:
				// payment is ready to be mined
				if ($order->status != 'on-hold') {
					$this->debug(__METHOD__, 'PAID (awaiting confirmation), order: '.$order->id);
					$order->update_status('on-hold', __('Awaiting blockchain confirmation', 'woocommerce'));
				}
				break;
			case CvInvoiceNotification::InvoiceStatusConfirmed:
				// expected number of confirmations have made it into the blockchain
				$this->debug(__METHOD__, 'CONFIRMED (all done), order: '.$order->id);
				$order->payment_complete();
				break;
			case CvInvoiceNotification::InvoiceStatusComplete:
				// merchant paid out, ignore
				break;
			case CvInvoiceNotification::InvoiceStatusInvalid:
				// something bad happened, put order on failed and notify admin
				$order->update_status('failed', __('Order marked invalid by Coinvoice, '.
					'administrator action required.', 'woocommerce'));
				$mailer = WC()->mailer();
				$message = $mailer->wrap_message(__('Invalid invoice received', 'woocommerce'),
					sprintf(__('Order %s was marked invalid by Coinvoice.  Please contact support.',
					'woocommerce'), $order->get_order_number()));
				$mailer->send(get_option('admin_email'), sprintf(__('Order %s marked invalid',
					'woocommerce'), $order->get_order_number()), $message);
				break;
			case CvInvoiceNotification::InvoiceStatusCancelled:
				// invoice was canceled, mark order failed order and notify admin
				$order->update_status('failed', __('Order canceled by Coinvoice, '.
					'administrator action may be required.', 'woocommerce'));
				$mailer = WC()->mailer();
				$message = $mailer->wrap_message(__('Order canceled', 'woocommerce'),
					sprintf(__('Order %s was canceled by Coinvoice.',
					'woocommerce'), $order->get_order_number()));
				$mailer->send(get_option('admin_email'), sprintf(__('Order %s canceled',
					'woocommerce'), $order->get_order_number()), $message);
				break;
			default:
				// Figure this out
				$this->debug(__METHOD__, 'NOT HANDLED '.$coinvoiceNotify->Status);
				return;
			}
		}

		/**
		 * WC_coinvoice constructor.
		 *
		 * This function gets called by WooCommerce when an instantiation of WC_coinvoice is required.
		 *
		 * This function fills in all the defaults and registers actions as required by WooCommerce.
		 *
		 * @access public
		 * @param void
		 * @return void
		 */
		public function __construct() {
			$this->log = new WC_Logger();

			$this->id = 'coinvoice';
			$this->icon = plugin_dir_url(__FILE__).'coinvoice.png';
			$this->has_fields = false;
			$this->method_title = 'Coinvoice';
			$this->method_description = 'Coinvoice payment gateway';
			$this->debug = $this->get_option('debug');

			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->settings['title'];
			if ($this->get_option('mode') === 'redirect') {
				$this->order_span_text = __('Proceed to Coinvoice', 'woocommerce');
			} else {
				$this->order_span_text = __('Proceed to checkout with Coinvoice', 'woocommerce');
			}

			// save hook for settings
			add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this,
				'process_admin_options'));

			// enable callback when coinvoice invoice is confirmed
			add_action('woocommerce_api_wc_coinvoice', array($this, 'coinvoice_callback'));

			// intercept receipt page
			add_action('woocommerce_receipt_coinvoice', array(&$this, 'receipt_page'));

			// add some scripts when needed
			wp_register_script(self::qrcode, plugin_dir_url(__FILE__).'assets/js/qrcode.js');
			wp_register_script(self::timer, plugin_dir_url(__FILE__).'assets/js/cv_timer.js');

			// style it up
			wp_register_style(self::styleCoinvoice, plugin_dir_url(__FILE__).'assets/css/app.css');

			// schedule scripts and style
			add_action('wp_enqueue_scripts', array(&$this, 'woocommerce_coinvoice_scripts'));
		}

		/**
		 * Setup coinvoice settings in WooCommerce "Payment Gateways" tab.
		 *
		 * @access public
		 * @param void
		 * @return void
		 */
		public function init_form_fields() {
			$this->form_fields = array(
			    // required plugin entries
			    'enabled' => array(
				    'title'=>__('Enable/disable', 'woocommerce'),
				    'type'=>'checkbox',
				    'label'=>__('Enable Coinvoice payments for woocommerce', 'woocommerce'),
				    'default'=>'yes',
				    ),
			    'title'=>array(
				    'title'=>__('Title', 'woocommerce'),
				    'type'=>'text',
				    'description'=>__('This controls the title which the user sees during checkout.',
					'woocommerce'),
				    'default'=>__('Coinvoice payment', 'woocommerce'),
				    'desc_tip'=>true,
				    ),
			    'description'=>array(
				    'title'=>__('Customer message', 'woocommerce'),
				    'type'=>'textarea',
				    'placeholder'=>__('Replace this text with your message!', 'woocommerce'),
				    'description'=>__('Message customer will see when using Coinvoice', 'woocommerce'),
				    'default'=>__('Thank you for using Coinvoice.', 'woocommerce'),
				    'desc_tip'=>true,
				    ),
			    'api_key'=>array(
				    'title'=>__('API key', 'woocommerce'),
				    'type'=>'text',
				    'placeholder'=>__('Replace this text with your API key!', 'woocommerce'),
				    'description'=>__('An API key must be generated on the coinvoice website.'.
				    'This key can be obtained by clicking on "Manage API" and following the instructions.',
					    'woocommerce'),
				    'desc_tip'=>true,
			        ),
			    'mode' => array(
				    'title'=>__('Payment mode', 'woocommerce'),
				    'type'=>'select',
				    'description'=> __('Choose method of payment.', 'woocommerce'),
				    'default'=>'direct',
				    'desc_tip'=>true,
				    'options'=>array(
				    	'redirect'=>__('Hosted checkout', 'woocommerce'),
				    	'direct'=>__('Payment on checkout page', 'woocommerce')
					)
				    ),
			    'debug' => array(
				    'title'=>__('Debug', 'woocommerce'),
				    'type'=>'checkbox',
				    'description'=>__('When checked the coinvoice plugin will log debug output to: '.
					    sprintf('<code>woocommerce/logs/coinvoice-%s.txt</code>',
					    sanitize_file_name(wp_hash('coinvoice')))),
				    'label'=>__('Enable Coinvoice debug', 'woocommerce'),
				    'default'=>'no',
				    ),
			    'sandbox' => array(
				    'title'=>__('Sandbox mode', 'woocommerce'),
				    'type'=>'checkbox',
				    'description'=>__('When checked the coinvoice plugin will use the sandbox '.
					'infrastructure.'),
				    'desc_tip'=>true,
				    'label'=>__('Enable sandbox mode', 'woocommerce'),
				    'default'=>'no',
				    ),
			//    'development' => array(
			//	    'title'=>__('Development mode', 'woocommerce'),
			//	    'type'=>'checkbox',
			//	    'description'=>__('Coinvoice employees only, please do not use.'),
			//	    'label'=>__('Enable Coinvoice development mode', 'woocommerce'),
			//	    'default'=>'no',
			//	    ),
			    );
			// This is a trick to add extra development options.  This is for coinvoice use only
			if ($this->get_option('development') === 'yes') {
				$this->form_fields['coinvoice_url'] = array(
					'title'=>__('Coinvoice URL', 'woocommerce'),
					'type'=>'text',
					'description'=>__('Coinvoice employees only, please do not use.'),
					'default'=>'http://10.170.0.100:9000',
				);
			}
		}

		/**
		 * Generate HTML for coinvoice settings in WooCommerce "Payment Gateways" tab.
		 *
		 * This function adds some additional text and logo to the settings page.
		 *
		 * @access public
		 * @param void
		 * @return void
		 */
		public function admin_options() {
			?>
			<img style="float:right" src="<?php echo plugin_dir_url(__FILE__); ?>coinvoice.png" />
			<h3><?php _e('Coinvoice', 'woocommerce');?></h3>

			<?php if ($this->get_option('api_key') === '') : ?>
			<div class="coinvoice updated">
			  <p class="main"><strong><?php _e('Get started with Coinvoice', 'woocommerce'); ?></strong></p>
			  <span>
			    <a href="https://coinvoice.com/">Coinvoice</a>
			    <?php _e('Coinvoice is a payment processor that allows merchants to invoice in either '.
			      'U.S Dollars ("USD") or bitcoin ("BTC") and receive either BTC or USD as payments for '.
			      'goods and services worldwide.   Coinvoice makes it easy for any merchant to receive BTC '.
			      'as a form of incoming or outgoing payment without them or their customers having to '.
			      'worry about the infrastructure necessary to conduct and process these transactions.  '.
			      'Coinvoice provides you with a private, reliable and secure way for your business to '.
			      'receive BTC.', 'woocommerce'); ?>
			  </span>

			  <p>
			    <a href="https://coinvoice.com/register" target="_blank" class="button button-primary">
			      <?php _e('Signup for free', 'woocommerce'); ?>
			    </a>
			    <a href="https://coinvoice.com/faq" target="_blank" class="button">
			      <?php _e('Learn more about Coinvoice', 'woocommerce'); ?>
			    </a>
			  </p>
			</div>

			<?php else : ?>

			<p>
			  <a href="https://coinvoice.com/">Coinvoice</a>
			  <?php _e('provides a transparent way for customers to pay with Bitcoin.  '.
			    "We'll worry about the Bitcoin infrastructure, you get paid in USD!", 'woocommerce'); ?>
			</p>
			<?php endif; ?>

			<table class="form-table">
			  <?php $this->generate_settings_html(); ?>
			</table><!--/.form-table-->
			<?php
		}

		/**
		 * Workhorse function that is required by WooCommerce to proxy a WooCommerce order into an external
		 * payment gateway.
		 *
		 * This function gets called by WooCommerce when an order has been submitted and is ready to be paid.
		 *
		 * The coinvoice payment gateway supports two modes of payment methods.
		 * Hosted (pay on the coinvoice site) or embedded (pay on the receipt page).
		 * This function determines which mode to use based on the administrator settings.
		 *
		 * @access public
		 * @param string $order_id rendezvous token that identifies a WooCommerce order.
		 * @return void
		 */
		public function process_payment($order_id) {
			if ($this->get_option('mode') === 'redirect') {
				return $this->process_payment_redirect($order_id);
			}
			return $this->process_payment_direct($order_id);
		}

		/**
		 * Embedded payment method.
		 *
		 * This function hooks the receipt page in order to render the coinvoice payment HTML on it.
		 *
		 * @access public
		 * @param string $order_id rendezvous token that identifies a WooCommerce order.
		 * @return void
		 */
		public function process_payment_direct($order_id) {
			$order = new WC_Order($order_id);
			return array (
				'result'=>'success',
				'redirect'=>add_query_arg('order', $order->id,
				    add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
			);
		}

		/**
		 * Hosted payment method.
		 *
		 * This function proxies the WooCommerce order into a coinvoice invoice and redirects to coinvoice.com
		 * for payment processing.
		 *
		 * @access public
		 * @param string $order_id rendezvous token that identifies a WooCommerce order.
		 * @return void
		 */
		public function process_payment_redirect($order_id) {
			if (!$this->post_invoice($order_id, $coinvoiceReply)) {
				return;
			}

			// see if we need to go to a test box
			if ($this->get_option('development') === 'yes') {
				$coinvoice_url = $this->get_option('coinvoice_url');
			} else {
				$coinvoice = new Coinvoice(); // make this static!
				if ($this->get_option('sandbox') === 'yes') {
					$coinvoice_url = $coinvoice->GetSandboxHostName();
				} else {
					$coinvoice_url = $coinvoice->GetHostName();
				}
			}

			$order = new WC_Order($order_id);
			return array(
			    'result'=>'success',
			    'redirect'=>$coinvoice_url.'/pay/'.$coinvoiceReply->PaymentLinkId.
				'/'.base64_encode($this->get_return_url($order)),
			);
		}

		/**
		 * Recipt page hook.
		 *
		 * This function proxies the WooCommerce order into a coinvoice invoice and renders the coinvoice
		 * payment widget on the receipt page for payment processing.
		 *
		 * @access public
		 * @param string $order_id rendezvous token that identifies a WooCommerce order.
		 * @return void
		 */
		public function receipt_page($order_id) {
			if (!$this->post_invoice($order_id, $coinvoiceReply)) {
				return;
			}

			$link = 'bitcoin:'.$coinvoiceReply->BtcAddress.
				'?amount='.$coinvoiceReply->TotalBtc.
				'&label=Coinvoice';
			?>
			<p>
				Thank you for your order, please pay as indicated
			</p>
			<div id="btcPaymentInfo">
				<span id="paymentCode" style="display:none;"><?php echo $coinvoiceReply->PaymentLinkId; ?></span>
				<span id="endTime" style="display:none;"><?php echo date('c',$coinvoiceReply->ExpirationTime); ?></span>
				<div class="green">
					<div id="topRow">
						<div id="timer">
							<span class="timer_icon"></span>
							<span class="timer text-center" id="timeLeft"></span>
						</div>
						<div id="payButton">
							<a href="<?php $link ?>" class="btclink">Pay with local wallet</a>
						</div>
						<table class="specialTable">
							<thead>
								<tr>
									<th>Address</th>
									<th>Btc to pay</th>
									<th>Btc/Usd rate</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td id="vcAddress"><?php echo $coinvoiceReply->BtcAddress ?></td>
									<td id="toBePaid"><?php echo $coinvoiceReply->TotalBtc ?></td>
									<td><?php echo round($coinvoiceReply->BtcQuoteRate, 2) ?></td>
								</tr>
							</tbody>
						</table>
					</div>
					<div id="qrArea">
						<div id="qrcode" class="qrcode"></div>
					</div>
					<div id="partialPaymentInfo">
					</div>
				</div>
			</div>	
			<div id="timerUp" class="green" style="display:none;">
				<h1 class="text-center">Please refresh this page.</h1>
				<p class="text-center">To receive a current BTC price you must reload this page.  If you have already sent your payment this page will automatically update once we receive the first confirmation.</p>
			</div>
			<div id="paid" class="green" style="display:none;">
				<h1 class="text-center">Payment Received!</h1>
				<div id="paidTable">
					<table class="bordered">
						<tbody>
							<tr>
								<th>Btc address</th><td id="btcAddress"><?php echo $coinvoiceReply->BtcAddress ?></td>
							</tr>
							<tr id="btcPaid">
								<th>Amount</th>
							</tr>
							<tr id="btcPaidTime">
								<th>Paid time</th>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			<div id="txFound" class="green" style="display:none;">
				<h3 class="text-center lato">Payment Received</h3>
				<div id="txFoundTable">	
					<table class="bordered">
						<tbody>
							<tr id="btcAddress">
								<th>Btc address</th><td><?php echo $coinvoiceReply->BtcAddress ?></td>
							</tr>
							<tr id="txFoundAmt">
								<th>Amount</th>
							</tr>
							<tr id="txFoundTime">
								<th>Paid time</th>
							</tr>
						</tbody>
					</table>
				</div>
				<div id="confirmationsArea">
					<h3 class="text-center">Confirmations</h3>
					<div class="container">
						<ul id="confirms">
							<li id="confirm1">1<div id="conf1" class="conf"></div><div id="pulse1" class="pulse"></div></li>
							<li id="confirm2">2<div id="conf2" class="conf"></div><div id="pulse2" class="pulse"></div></li>
							<li id="confirm3">3<div id="conf3" class="conf"></div><div id="pulse3" class="pulse"></div></li>
							<li id="confirm4">4<div id="conf4" class="conf"></div><div id="pulse4" class="pulse"></div></li>
							<li id="confirm5">5<div id="conf5" class="conf"></div><div id="pulse5" class="pulse"></div></li>
							<li id="confirm6">6<div id="conf6" class="conf"></div><div id="pulse6" class="pulse"></div></li>
						</ul>
					</div>
				</div>
			</div>
			<div id="errorPayment" class="green" style="display:none;">
				<h3 class="text-center lato">An error occured.</h3>
				<span class="text-center error", id="errorFromServer">
					Our serve has unexpectedly disconnected.  Please reload this page
				</span>
				<div id="errorMessageArea">
				</div>
			</div>
			<?php
		}

		/**
		 * Translate WooCommerce order into coinvoice invoice and POST invoice to coinvoice.com.
		 *
		 * This function uses the coinvoice API to translate the order into the proper JSON format.
		 * It also sets up some additional fields in coinvoice object based on administrator settings.
		 *
		 * In order to handle notifications properly three things must happen.
		 *
		 * 1. Set the NotificationURL.
		 *
		 * 2. Create a "unique enough" key that can be used to determine if an order has been POSTED before.
		 * This key is set in the AlternateInvoiceKey field.
		 *
		 * 3. Create a rendezvous token that can be used to lookup a WooCommerce order in the callback.
		 * This token is set in the InternalInvoiceId.
		 *
		 * AlternateInvoiceKey MUST be unique per WooCommerce order and user!
		 * If the key in AlternateInvoiceKey is duplicate then coinvoice will return the previously posted
		 * order.
		 * This is used to prevent orders being submitted more than once to coinvoice.
		 *
		 * @access public
		 * @todo determine how to handle order statuses in this function.
		 * @param string $order_id rendezvous token that identifies a WooCommerce order.
		 * @param CvInvoiceReply $reply passed by reference, returned if successful.
		 * @return boolean true if successful or false if unsuccessful.
		 */
		public function post_invoice($order_id, &$reply) {
			$wc_order = new WC_Order($order_id);

			// create invoice
			$invoiceRequest = new CvInvoiceRequest();
			$invoiceRequest->PayerName     = $wc_order->billing_first_name.' '.$wc_order->billing_last_name;
			$invoiceRequest->PayerAddress1 = $wc_order->billing_address_1;
			$invoiceRequest->PayerAddress2 = $wc_order->billing_address_2;
			$invoiceRequest->PayerCity     = $wc_order->billing_city;
			$invoiceRequest->PayerState    = $wc_order->billing_state;
			$invoiceRequest->PayerZip      = $wc_order->billing_postcode;
			$invoiceRequest->PayerCountry  = $wc_order->billing_country;
			$invoiceRequest->PayerPhone    = $wc_order->billing_phone;
			$invoiceRequest->PayerEmail    = $wc_order->billing_email;
			$invoiceRequest->PayerCurrency = 'BTC';
			$invoiceRequest->PriceCurrency = get_woocommerce_currency();
			if ($invoiceRequest->PriceCurrency !== 'USD') {
				$this->debug(__METHOD__, "post_invoice: invalid PriceCurrency");
				CvError(__('Internal error(1): ', 'woothemes').'currently only USD is supported '.
					'for PriceCurrency');
				return false;
			}
			// do we need $wc_order->billing_company ?

			// create line items
			$wc_items = $wc_order->get_items();
			$total = 0.0;
			$itemCount = 0;
			foreach($wc_items as $wc_item) {
				$product = $wc_order->get_product_from_item($wc_item);

				// transmogrify woocommerce item to coinvoice item
				$item = new CvItem();
				$item->ItemCode= $product->get_sku();
				$item->ItemDesc  = $wc_item['name'];
				$item->ItemQuantity  = $wc_item['qty'];
				if (get_option('woocommerce_prices_include_tax') === 'yes') {
					$line_total = $wc_order->get_line_subtotal($wc_item, true /*tax*/, true /*round*/);
				} else {
					$line_total = $wc_order->get_line_subtotal($wc_item, false /*tax*/, true /*round*/);
				}
				$item->ItemPricePer  = sprintf("%.2f", $line_total / $wc_item['qty']);
				if ($invoiceRequest->ItemAdd($item, $error) === false) {
					$this->debug(__METHOD__, "post_invoice->ItemAdd $error");
					CvError(__('Internal error(2): ', 'woothemes').$error);
					return false;
				}
				$total = $total + $line_total;
				$itemCount++;
			}
			$this->debug(__METHOD__, "line items: ".$total);
			// tax, don't include if tax is already part of line items
			if ($wc_order->get_total_tax() != 0 && get_option('woocommerce_prices_include_tax') !== 'yes') {
				$item = new CvItem();
				$item->ItemCode= '';
				$item->ItemDesc  = __('Sales tax', 'woothemes');
				$item->ItemQuantity  = '1';
				// woocomerce get_total_tax() method doesn't round correctly
				// so calculate taxes and round ourselves
				foreach ($wc_order->get_tax_totals() as $value) {
					$tax += $value->amount;
				}
				$tax = round($tax, 2);
				$item->ItemPricePer = sprintf("%.2f", tax);
				if ($invoiceRequest->ItemAdd($item, $error) === false) {
					$this->debug(__METHOD__, "post_invoice->ItemAdd $error");
					CvError(__('Internal error(2.1): ', 'woothemes').$error);
					return false;
				}
				$total = $total + $tax;
			}

			// shipping
			if ($wc_order->get_total_shipping() != 0) {
				$item = new CvItem();
				$item->ItemCode= '';
				$item->ItemDesc  = __('Shipping and handling', 'woothemes');
				$item->ItemQuantity  = '1';
				$shipping = round($wc_order->get_total_shipping(), 2);
				if (get_option('woocommerce_prices_include_tax') === 'yes') {
					$shipping += round($wc_order->get_shipping_tax(), 2);
				}
				$item->ItemPricePer = sprintf("%.2f", $shipping);
				if ($invoiceRequest->ItemAdd($item, $error) === false) {
					$this->debug(__METHOD__, "post_invoice->ItemAdd $error");
					CvError(__('Internal error(2.2): ', 'woothemes').$error);
					return false;
				}
				$total = $total + $shipping;
			}
			$this->debug(__METHOD__, "shipping: ".$shipping);
			// coupens
			if ($wc_order->get_total_discount() != 0) {
				$item = new CvItem();
				$item->ItemCode= '';
				$item->ItemDesc  = __('Discounts', 'woothemes');
				$item->ItemQuantity  = '1';
				$discount = round($wc_order->get_total_discount(), 2);
				$item->ItemPricePer = sprintf("-%.2f", $discount);
				if ($invoiceRequest->ItemAdd($item, $error) === false) {
					$this->debug(__METHOD__, "post_invoice->ItemAdd $error");
					CvError(__('Internal error(2.3): ', 'woothemes').$error);
					return false;
				}
				$total = $total - $discount;
			}
			$this->debug(__METHOD__, "discount: ".$discount);
			$this->debug(__METHOD__, "total calculated: ".$total);
			$this->debug(__METHOD__, "total: ".$wc_order->get_total());

			// assert order total
			if (abs($wc_order->get_total() - $total) > 0.01) {
				$this->debug(__METHOD__, 'Totals do not match got '.$total.' wanted '.$wc_order->get_total());
				CvError(__('Internal error(2.4): Please contact the administrator', 'woothemes'));
				return false;
			}

			// settings part
			$invoiceRequest->NotificationURL = str_replace('https:', 'http:',
				add_query_arg('wc-api', 'wc_coinvoice', home_url('/')));
			$invoiceRequest->InternalInvoiceId = base64_encode(serialize(
				array($wc_order->id, $wc_order->order_key)));

			// create a unique enough key to cause duplicate order collisions
			$data = $wc_order->id.$invoiceRequest->PayerName.$invoiceRequest->PayerEmail.$total.$itemCount;
			$key = get_site_url();
			$invoiceRequest->AlternateInvoiceKey = hash_hmac('sha256', $data, $key, false);

			if ($this->get_option('sandbox') === 'yes') {
				$this->debug(__METHOD__, 'SANDBOX');
				$invoiceRequest->TestInvoice = 'yes';
			}

			if (!$invoiceRequest->Marshal($json, $error)) {
				$this->debug(__METHOD__, "post_invoice->Marshal $error");
				CvError(__('Internal error(3): ', 'woothemes').$error);
				return false;
			}
			$this->debug(__METHOD__, 'out: '.$json);

			// send off to coinvoice.com
			$coinvoice = new Coinvoice();
			$coinvoice->SetPostFunctionName('CvPost');
			$coinvoice->SetApiKey($this->get_option('api_key'));

			if ($this->get_option('development') === 'yes') {
				$coinvoice_url = $this->get_option('coinvoice_url');
				$coinvoice->SetHost($coinvoice_url);
				$this->debug(__METHOD__, "DEVELOPMENT MODE POST: ".$coinvoice_url);
			}

			if (!$coinvoice->Post($json, $reply, $error)) {
				$this->debug(__METHOD__, "post_invoice->Post $error");
				CvError(__('Internal error(4): ', 'woothemes').$error.' '.$reply);
				return false;
			}

			$this->debug(__METHOD__, 'in: '.var_export($reply, true));

			// decode reply from coinvoice
			$coinvoiceReply = new CvInvoiceReply();
			if (!$coinvoiceReply->Unmarshal($reply, $error)) {
				$this->debug(__METHOD__, "post_invoice->Unmarshal $error");

				// see if we got a better error to show user
				$CvFailure = new CvFailure();
				if ($CvFailure->Unmarshal($reply, $error)) {
					// successfully unmarshaled, use ErrorCode instead
					$error = $CvFailure->ErrorCode;
				}
				$this->debug(__METHOD__, "post_invoice->Unmarshal user error: $error");

				CvError(__('Internal error(5): ', 'woothemes').$error);
				return false;
			}

			if ($coinvoiceReply->Status !== CvInvoiceNotification::InvoiceStatusNew) {
				// TODO do something with other invoice stati
				CvError(__('Internal error(6): status = ', 'woothemes').$coinvoiceReply->Status);
				return false;
			}

			$reply = $coinvoiceReply;
			return true;
		}
	}

	/**
	 * Add coinvoice payment gateway to the list of available gateways in WooCommerce.
	 *
	 * @access public
	 * @param array available gateways.
	 * @return void
	 */
	function woocommerce_add_coinvoice_gateway($methods) {
		$methods[] = 'WC_coinvoice';
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_coinvoice_gateway');
}
