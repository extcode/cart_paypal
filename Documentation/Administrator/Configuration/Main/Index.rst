.. include:: ../../../Includes.rst.txt

.. _main-configuration:

==================
Main Configuration
==================

The settings are site settings of the set **Cart PayPal**
(`extcode/cart-paypal`) and can be edited in the backend module *Site
Settings*. Without the site set, the same names are TypoScript constants of
the static TypoScript.

.. code-block:: yaml
   :caption: config/sites/<site>/settings.yaml

   plugin:
     tx_cartpaypal:
       paymentPid: 42
       brandName: 'Example Shop'
       locale: de-DE

.. code-block:: typoscript
   :caption: TypoScript constants, without site set

   plugin.tx_cartpaypal {
       paymentPid = 42
       brandName = Example Shop
       locale = de-DE
   }

|

.. container:: table-row

   Property
      plugin.tx_cartpaypal.paymentPid
   Data type
      int (page)
   Description
      The page with the content element "Cart: PayPal payment", see
      :ref:`Payment page <payment-page>`. With 0 the customer is redirected to
      PayPal directly after the order.
   Default
      0

.. container:: table-row

   Property
      plugin.tx_cartpaypal.redirectTypeNum
   Data type
      int
   Description
      The page type of the endpoints: the webhook, the return of the customer
      from PayPal and the requests of the JavaScript on the payment page.
   Default
      2278101

.. container:: table-row

   Property
      plugin.tx_cartpaypal.brandName
   Data type
      string
   Description
      The name of the shop shown by PayPal, at most 127 characters. Without a
      name PayPal shows the name of the PayPal account.

.. container:: table-row

   Property
      plugin.tx_cartpaypal.locale
   Data type
      string
   Description
      The language of the PayPal pages as BCP-47 code, e.g. `de-DE`. Without a
      locale PayPal chooses the language.

.. container:: table-row

   Property
      plugin.tx_cartpaypal.shippingPreference
   Data type
      string
   Description
      `NO_SHIPPING`: PayPal asks for no shipping address, Cart already has the
      addresses of the order. `GET_FROM_FILE`: The customer chooses a shipping
      address stored at PayPal; it is not transferred to the order.
   Default
      NO_SHIPPING

The credentials are no site settings, see :ref:`Credentials <credentials>`.
