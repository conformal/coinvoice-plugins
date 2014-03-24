VERSION = `git log --pretty=format:'%h' -n 1`
QUIET_SCP ?= "-q"
DOCDIR = coinvoice_api_v1
WOODOCDIR = woocommerce/docs
D6DOCDIR = d6_ubercart/docs
D6UC ?= "coinvoice-drupal6-${VERSION}"

all: deps zip php phpdoc sha256 depsdone test man woocommerce d6_ubercart

deps:
	@echo -n "checking dependencies: "

depsdone:
	@echo

zip:
	@zip -h > /dev/null
	@echo -n "zip"

php:
	@php -h > /dev/null
	@echo -n ", php"

phpdoc:
	@phpdoc --quiet
	@echo -n ", phpdoc"

sha256:
	@echo | sha256 -q > /dev/null
	@echo -n ", sha256 found "

test:
.if !defined(APIKEY)
	@echo "APIKEY (generated at sandbox.coinvoice.com) must be set"; exit 1
.endif
	@echo "testing generic API"
	@php coinvoice/v1/coinvoice_ut.php -die

man:
	@echo "building PHP API documentation version 1"
	@phpdoc --quiet -f coinvoice/v1/coinvoice.php -t ${DOCDIR}
	@echo "building WooCommerce documentation"
	@phpdoc --quiet -f woocommerce/wc_coinvoice.php -t ${WOODOCDIR}
	@echo "building Drupal 6 documentation"
	@phpdoc --quiet -f d6_ubercart/uc_coinvoice.pages.inc -f d6_ubercart/uc_coinvoice.module -t ${D6DOCDIR}

d6_ubercart: man
	@echo "building d6_ubercart plugin version ${VERSION} "
	@mkdir -p tmp/${D6UC}
	@cp -pR coinvoice d6_ubercart/* tmp/${D6UC}/
	@cd tmp && zip ../coinvoice-drupal6-${VERSION} -qr .
	@cd tmp && tar zcf ../coinvoice-drupal6-${VERSION}.tgz coinvoice-drupal6-${VERSION}
	@sha256 coinvoice-drupal6-${VERSION}.zip
	@sha256 coinvoice-drupal6-${VERSION}.tgz

woocommerce: man
	@echo "building woocommerce plugin version ${VERSION} "
	@cp -pR coinvoice woocommerce/
	@cd woocommerce && zip ../coinvoice-woocommerce-${VERSION} -qr .
	@sha256 coinvoice-woocommerce-${VERSION}.zip

clean:
	rm -f *.zip *.tgz
	rm -rf ${DOCDIR} ${WOODOCDIR} ${D6DOCDIR}
	rm -rf tmp
	rm -rf woocommerce/coinvoice
	rm -rf output

install-drupal6:
.if !defined(WWW)
	@echo "WWW (hostname) and TARGETDIR (drupal6 plugin directory) must be set"; exit 1
.endif
.if !defined(TARGETDIR)
	@echo "WWW (hostname) and TARGETDIR (drupal6 plugin directory) must be set"; exit 1
.endif
	@echo Installing d6_ubercart to ${WWW}:${TARGETDIR}
	@ssh ${WWW} "rm -rf ${TARGETDIR}/coinvoice-d6_ubercart-dev && mkdir -p ${TARGETDIR}/coinvoice-d6_ubercart-dev"
	@scp -r ${QUIET_SCP} coinvoice d6_ubercart/* ${WWW}:${TARGETDIR}/coinvoice-d6_ubercart-dev/
	@ssh ${WWW} "chmod -R 777 ${TARGETDIR}/coinvoice-d6_ubercart-dev"

install-woocommerce:
.if !defined(WWW)
	@echo "WWW (hostname) and TARGETDIR (woocommerce plugin directory) must be set"; exit 1
.endif
.if !defined(TARGETDIR)
	@echo "WWW (hostname) and TARGETDIR (woocommerce plugin directory) must be set"; exit 1
.endif
	@echo Installing woocommerce to ${WWW}:${TARGETDIR}
	@ssh ${WWW} "rm -rf ${TARGETDIR}/coinvoice-woocommerce-dev && mkdir -p ${TARGETDIR}/coinvoice-woocommerce-dev"
	@scp -r ${QUIET_SCP} coinvoice woocommerce/* ${WWW}:${TARGETDIR}/coinvoice-woocommerce-dev/
	@ssh ${WWW} "chmod -R 777 ${TARGETDIR}/coinvoice-woocommerce-dev"

.PHONY: all deps depsdone zip php phpdoc sha256 test man woocommerce d6_ubercart clean install-woocommerce
