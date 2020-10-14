# LampSBtcPayShopware

## Brief Description

LampSBtcPayShopware is an Shopware 5 plugin which helps you to accept crypto currencies on your Shopware Shop.

The following services are supported as a payment gateway by the plugin:

* [BTCPayServer](https://btcpayserver.org/) selfhosted service
* [BitPay](https://bitpay.com/) service by BitPay Inc.

This enables you to accept payments from all common crypto currencies, such as Bitcoin, Bitcoin Lightning, Dash, Etherum, Bitcoin Cash, ...

## Building

To create an installable plugin zip file for the Shopware 5 Backend, you have to do the following steps.

### Install dependencies via composer

```
composer --working-dir=./LampSBTCPay/ install --no-dev
```

### Creating a zip archive

```
zip -r LampSBTCPay.zip LampSBTCPay
```

## Compatibility

The plugin uses the [bitpay json protocol](https://bitpay.com/docs/payment-protocol), so every service which implements the protocol can be used.

* [BTCPayServer](https://btcpayserver.org/) selfhosted service
* [BitPay](https://bitpay.com/) service by BitPay Inc.


## Shopware compatibility

The plugin is tested with Shopware 5.5 und Shopware 5.6

## License

The contents of the LampSBTCPay folder is released unter the [MIT License](./LampSBTCPay/LICENSE).

## Sponsors

### Primary Sponsor

[![SATOSHIGOODS](sponsors/lampsolutions.png)](https://www.lamp-solutions.de/)
<br/><br/>

### Other Sponsors
<br/><br/>
[![SATOSHIGOODS](sponsors/shopinbit.png)](https://shopinbit.de/)
<br/><br/><br/><br/>
[![SATOSHIGOODS](sponsors/satoshigoods.png)](https://satoshigoods.de/)
<br/><br/>
