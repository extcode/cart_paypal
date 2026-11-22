.. include:: ../../Includes.rst.txt

===========
8.0 Changes
===========

The version 8.x is a release for TYPO3 v13.4 and extcode/cart 11.x. It is a
rewrite, nothing of the previous versions is kept.

Breaking
========

* The payment uses the PayPal Orders v2 API instead of PayPal Payments
  Standard and IPN. The PayPal account needs an app with client id and secret,
  see :ref:`Credentials <credentials>`. The setting `business` is gone.
* The settings `sendEachItemToPaypal`, `sendEachItemToPaypalTitle`, `debug`
  and `curl_timeout` are gone. PayPal gets the total of the order only.
* The events `SuccessEvent` and `NotifyEvent` are replaced by
  `\Extcode\CartPaypal\Event\Order\FinishEvent`, see :ref:`Events <events>`.
* The actions `success`, `cancel` and `notify` of the plugin `Cart` are
  replaced by the plugin `Api`. Old IPN URLs are no longer answered.

Features
========

* The payment page shows the PayPal button of the PayPal JavaScript SDK v6,
  see :ref:`Payment page <payment-page>`.
* The payment method `PAYPAL_CREDITCARD` lets the customer pay with a debit
  or credit card without a PayPal account.
* Webhooks finish the order if the customer does not return to the shop, see
  :ref:`Webhook <webhook>`.
* The PayPal order is stored as transaction of the payment, no field is added
  to the tables of Cart.
* The settings are available as site settings of the site set
  `extcode/cart-paypal`, see :ref:`Main Configuration <main-configuration>`.
* The credentials are read from `$GLOBALS['TYPO3_CONF_VARS']`, e.g. set from
  environment variables, or per site from the site configuration, see
  :ref:`Credentials <credentials>`.
