VERSION = `git log --pretty=format:'%h' -n 1`
QUIET_SCP ?= "-q"
DOCDIR = coinvoice_api_v1_documentation
WOODOCDIR = docs
D6DOCDIR = docs
D6UC ?= coinvoice-drupal6-${VERSION}
WOOCOMMERCE ?= coinvoice-woocommerce-${VERSION}

all: deps zip php phpdoc sha256 depsdone test coinvoice woocommerce d6_ubercart

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

prestuff:
	@mkdir -p tmp/

coinvoice: prestuff
	@echo "building PHP API documentation version 1"
	@phpdoc --quiet -f coinvoice/v1/coinvoice.php -t tmp/${DOCDIR}-${VERSION}
	@echo "building coinvoice API version ${VERSION} "
	@cp -pR coinvoice tmp/coinvoice-${VERSION}
	@cd tmp && tar zcf ../coinvoice-${VERSION}.tgz coinvoice-${VERSION}
	@cd tmp && tar zcf ../${DOCDIR}-${VERSION}.tgz ${DOCDIR}-${VERSION}
	@sha256 ${DOCDIR}-${VERSION}.tgz
	@sha256 coinvoice-${VERSION}.tgz
	@sha256 ${DOCDIR}-${VERSION}.tgz > ${DOCDIR}-${VERSION}.tgz
	@sha256 coinvoice-${VERSION}.tgz > coinvoice-${VERSION}.tgz

d6_ubercart: prestuff
	@echo "building d6_ubercart plugin version ${VERSION} "
	@mkdir -p tmp/${D6UC}/
	@cp -pR coinvoice d6_ubercart/* tmp/${D6UC}/
	@echo "building Drupal 6 documentation"
	@phpdoc --quiet -f d6_ubercart/uc_coinvoice.pages.inc -f d6_ubercart/uc_coinvoice.module -t tmp/${D6UC}/${D6DOCDIR}
	@cd tmp && zip ${D6UC} -qr ${D6UC} && mv ${D6UC}.zip ../
	@cd tmp && tar zcf ../${D6UC}.tgz ${D6UC}
	@sha256 ${D6UC}.zip
	@sha256 ${D6UC}.tgz
	@sha256 ${D6UC}.zip > ${D6UC}.zip.digest
	@sha256 ${D6UC}.tgz > ${D6UC}.tgz.digest

woocommerce: prestuff
	@echo "building woocommerce plugin version ${VERSION} "
	@mkdir -p tmp/${WOOCOMMERCE}/
	@cp -pR coinvoice woocommerce/* tmp/${WOOCOMMERCE}/
	@echo "building WooCommerce documentation"
	@phpdoc --quiet -f woocommerce/wc_coinvoice.php -t tmp/${WOOCOMMERCE}/${WOODOCDIR}
	@cd tmp && zip ${WOOCOMMERCE} -qr ${WOOCOMMERCE} && mv ${WOOCOMMERCE}.zip ../
	@sha256 ${WOOCOMMERCE}.zip
	@sha256 ${WOOCOMMERCE}.zip > ${WOOCOMMERCE}.zip.digest

clean:
	rm -f *.zip *.tgz *.digest
	rm -rf tmp output

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

.PHONY: all deps depsdone zip php phpdoc sha256 test prestuff woocommerce d6_ubercart clean install-woocommerce
