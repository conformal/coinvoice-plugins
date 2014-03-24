License
-------
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

Installation
------------

Configuration
-------------

Usage
-----

Instructions
Run make to create all plugins.  Documentation etc is also generated.  The make
targets are mostly for conformal internal use but we wanted to share this with
out users for documentation purposes.

NOTE: GNU make is assumed when make is mentioned.

Directories:
coinvoice   - common plugin code
woocommerce - plugin for woocommerce
d6_ubercart - plugin for drupal 6 ubercart 2

Special make targets:
The makefile can remotely deploy plugins.  This requires a cart specific method
so each plugin has it's own target.  For example, woocommerce installation is
installed using the "install-woocommerce" target.

Note that these deployment targets are for development purposes only.  These
targets should not be used since they leave the permissions at 777.

The current used installation environment variables are:
WWW       - the target host
TARGETDIR - the directory on the target host where the plugins are installed
QUIET_SCP - make this "" in order to show scp progress
APIKEY    - Set the API key for POST tests.  You can create one of these on
            sandbox.coinvoice.com

example installing woocommerce:
QUIET_SCP="" WWW=10.168.0.15 TARGETDIR=/var/www/wordpress/wp-content/plugins make install-woocommerce

example testing:
APIKEY=MYKEYGOESHERE make test
