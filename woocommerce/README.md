woocommerce
------------
woocommerce is the WooCommerce 2.x plugin for the Coinvoice payment gateway.

Official plugins and digests can be downloaded from [Conformal's
server](https://opensource.conformal.com/snapshots/coinvoice-plugins/woocommerce/).

## Requirements

WooCommerce plugin requires:
- PHP 5.3 or 5.4

## Installation

Download and verify plugin files.  Files should be downloaded from [Conformal's
server](https://opensource.conformal.com/snapshots/coinvoice-plugins/woocommerce/).
Download the latest version of the plugin archive.  Verify the digest of the
downloaded plugin archive.  For example on UNIX: ```sha256
coinvoice-woocommerce-e5db7ae.tgz```

If the digest is identical to the one advertised on the Conformal website then
move on to the next step.

The WooCommerce plugin can be installed by unzipping(1) the file in the WordPress
plugin directory or by using the WordPress GUI(2).

- (1) WordPress requires the administrator to extract the plugin archive file in
   the correct directory.  Typically this directory is at
   ```wp-content/plugins/``` from the WordPress base directory.  For example:
   ```/var/www/wordpress/wp-content/plugins```; note that this is an example
   and that it typically varies per web server.  To extract the zip archive do
   something along these lines: ```unzip coinvoice-woocommerce-e5db7ae.zip```.

-or-

- (2) Log in as the WordPress administrator and click on "Plugins"->"Add
   New"->"Upload"->"Choose file" and select the prior downloaded file.  Click
   on "Install Now" followed by "Activate Plugin".
   
At this point the plugin is installed and activated but it still needs to be
enabled and configured.

## Configuration

Follow the menu "WooCommerce"->"Settings"->"Checkout"->"Coinvoice".  Click on
the "Enable Coinvoice payments for woocommerce" checkbox.

It is strongly recommended to validate the plugin operation by using the
[Coinvoice sandbox](https://sandbox.coinvoice.com/) before enabling the plugin
for live use.  Simply sign up for an account on the
[sandbox](https://sandbox.coinvoice.com/), login and navigate to the "Manage
API" tab.  Click on the "Generate an API Key" button.  The API key is how
Coinvoice knows who is using the plugin.  The best way to transfer the key to
WooCommerce is by copying it from the Coinvoice sandbox site and pasting it
into the WooCommerce "API key" box.  Make sure to click on the "Enable sandbox
mode" checkbox before clicking on the "Save changes" button.  At this point the
plugin is fully setup in sandbox mode.

Go to the store front and create a test order and select Coinvoice as the
payment method.  Depending on the "Payment mode" setting either the payment
widget shows up embedded on the "Checkout" page or the user is redirected to
the Coinvoice payment gateway.  The next step is to pay for the order using
testnet bitcoins.  If everything is correct the widget will change and display
"Payment Received".  At this point the widget is awaiting confirmations.  Once
all 6 confirmations have been seen the widget will change once more to confirm
the entire payment.  The order status will go from "on-hold" (not paid yet) to
"Pending" (payment arrived on network) to "Processing" (6 block chain
confirmations) during this process.

NOTE: Conformal Systems can assist in obtaining testnet bitcoins in order to
validate a deployment.

If this process completes successfully then the plugin is ready to be enabled
for live use.

## Usage

Log in as the WordPress administrator and follow the menu
"WooCommerce"->"Settings"->"Checkout"->"Coinvoice".  Now copy and paste the API
key from the live [Coinvoice site](https://coinvoice.com) into the "API key"
edit box and make sure that the "Enable sandbox mode" checkbox is not checked.
Coinvoice recommends doing a transaction using bitcoin to make sure everything
works correctly.

## License

Copyright (c) 2014 Conformal Systems LLC. <support@conformal.com>

Permission to use, copy, modify, and distribute this software for any
purpose with or without fee is hereby granted, provided that the above
copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.

