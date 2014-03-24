d6_ubercart
------------
d6_ubercart is the Drupal 6 Ubercart 2 plugin for the Coinvoice payment
gateway.

Official plugins and digests can be downloaded from [Conformal's
server](https://opensource.conformal.com/snapshots/coinvoice-plugins/d6_ubercart/).

## Requirements

Drupal 6 Ubercart 2 plugin requires:
- PHP 5.3
- PHP cURL

## Installation

Download and verify plugin files.  Files should be downloaded from [Conformal's
server](https://opensource.conformal.com/snapshots/coinvoice-plugins/d6_ubercart/).
Download the latest version of the plugin archive (either the zip or the tar
file based on your needs).  Verify the digest of the downloaded plugin archive.
For example on UNIX:
```sha256 coinvoice-drupal6-e5db7ae.tgz```

If the digest is identical to the one advertised on the Conformal website then
move on to the next step.

Drupal 6 requires the administrator to extract the plugin archive file in the
correct directory.  Typically this directory is at ```sites/all/modules``` from
the Drupal 6 base directory.  For example:
```/var/www/htdocs/drupal6/sites/all/modules```; note that this is an example
and that it typically varies per webserver.

To extract the tar archive do something along these lines:
```tar zxf coinvoice-drupal6-e5db7ae.tgz```.

At this point the plugin is installed but it still needs to be enabled in
configured.

## Configuration

First the Coinvoice module must be enabled.  Log on as the site administrator
and follow the menu "Administer"->"Site building"->"Modules".  If the
installation was successful coinvoice should appear under the "Ubercart -
payment" pane.  Click on the "Coinvoice" checkbox followed by clicking on the
"Save configuration" button.  At this point the Coinvoice module is enabled and
the next step is to configure it.

Follow the menu "Administer"->"Store administration"->"Configuration"->"Payment
settings"->"Payment methods"->"Coinvoice settings".  It is strongly recommended
to validate the plugin operation by using the [Coinvoice
sandbox](https://sandbox.coinvoice.com/) before enabling the plugin for live
use.  Simply sign up for an account on the
[sandbox](https://sandbox.coinvoice.com/), login and navigate to the "Manage
API" tab.  Click on the "Generate an API Key" button.  The API key is how
Coinvoice knows who is using the plugin.  The best way to transfer the key to
Ubercart is by copying it from the Coinvoice sandbox site and pasting it into
the Ubercart "API key" box.  Make sure to click on the "Enable Coinvoice in
sandbox mode" checkbox before clicking on the "Save configuration" button.  At
this point the plugin is fully setup in sandbox mode.

Go to the store front and create a test order and select Coinvoice as the
payment method.  Depending on the "Payment mode" setting either the payment
widget shows up embedded on the "Review order" screen or the user is redirected
to the Coinvoice payment gateway.  The next step is to pay for the order using
testnet bitcoins.  If everything is correct the widget will change and display
"Payment Received".  At this point the widget is awaiting confirmations.  Once
all 6 confirmations have been seen the widget will change once more to confirm
the entire payment.  The order status will go from "Pending" (not paid yet) to
"Processing" (payment arived on network) to "Payment received" (6 blockchain
confirmations) during this process.

NOTE: Conformal Systems can assist in obtaining testnet bitcoins in order to
validate a deployment.

If this process completes successfully then the plugin is ready to be enabled
for live use.

## Usage

log in as the administrator and follow the menu "Administer"->"Store
administration"->"Configuration"->"Payment settings"->"Payment
methods"->"Coinvoice settings".  Now copy and paste the API key from the live
[Coinvoice site](https://coinvoice.com) into the "API key" edit box and make
sure that the "Enable Coinvoice in sandbox mode" checkbox is not checked.
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
