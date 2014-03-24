coinvoice-plugins
-----------------
coinvoice-plugins is the the current Coinvoice API and reference
implementations.

The Coinvoice API enables ecommerce/cart developers to integrate the Coinvoice
payment gateway service into websites.  This repository contains v1 of the
Coinvoice API and several working and tested reference implementations for a
number of carts.

Directory structure is as follows:
- coinvoice   - Coinvoice v1 reference API implementation
- woocommerce - Plugin for WooCommerce 2
- d6_ubercart - Plugin for Drupal 6 Ubercart 2

Each subdirectory contains additional instructions for that component.

## Requirements

In order to use the top-level make files the following commands are required:
- zip
- php 5.3 or 5.4
- phpdoc
- php cURL
- sha256
- BSD or GNU make

## Installation

Please consult individual directories for installation instructions for that
particular plugin.

## Testing

Reference API has been tested with PHP 5.3 and 5.4.  Where feasible plugins
have been tested with both versions of PHP however Drupal 6 requires PHP 5.3
and therefore the plugin has not been tested with PHP 5.4.

Conformal Systems provides a test environment for developers and sysadmins.
This environment uses testnet bitcoins and can therefore be used without
incurring actual costs.  Because the site accepts testnet bitcoins one can
validate a deployment from start to finish and know exactly what the live
result would look like.  The test site is 100% identical to the actual site
minus the KYC process.  All accounts are automatically created and accepted.
The test site can be found at
[https://sandbox.coinvoice.com](https://sandbox.coinvoice.com).  Feel free to
contact Conformal Systems If you need testnet bitcoins to validate a deployment
or implementation.

## Top-level usage

Run make to unit test and create all plugins and associated documentation.  The
top-level makefile is mostly for Conformal Systems LLC use however it is deemed
valuable information for plugin developers.

The plugins are created using their required native format.  In general this is
either a zip or tar file.  Additionally, SHA256 digests of the plugins are
generated as well.

When developing a plugin It is recommended to study the make files in the top
level directory.  It provides insight on how the official Coinvoice plugins are
tested, created and deployed.

Special make targets:
The makefile can remotely deploy plugins.  This requires a cart specific method
so each plugin has it's own target.  For example, woocommerce installation is
installed using the "install-woocommerce" target.

Environment variables:
- WWW       - the target host
- TARGETDIR - the directory on the target host where the plugins are installed
- QUIET_SCP - make this "" in order to show scp progress
- APIKEY    - Set the API key for POST tests.  You can create one of these on sandbox.coinvoice.com

Example installing woocommerce development version:
```QUIET_SCP="" WWW=1.1.1.1 TARGETDIR=/var/www/wordpress/wp-content/plugins make install-woocommerce```

Note that these deployment targets are for development purposes only.  These
targets should not be used since they leave the permissions at 777.

Example unit testing:
```APIKEY=MYKEYGOESHERE make test```

If a developer is interested in sharing their plugin with the wider community
please email Conformal Systems at <support@conformal.com>.  If the code is ISC
licensed and passes internal scrutiny and tests, Conformal Systems will be
happy to add it to the official repository.

## License

coinvoice-plugins is licensed under the [copyfree](http://copyfree.org) ISC License.

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
