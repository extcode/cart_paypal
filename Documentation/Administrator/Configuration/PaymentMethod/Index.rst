.. include:: ../../../Includes.rst.txt

============================
Payment Method Configuration
============================

The payment methods for PayPal are configured like any other payment method.
All configuration options of Cart are available. The customer chooses between
them in the cart, e.g. "PayPal" and "Credit card".

.. code-block:: typoscript

   plugin.tx_cart {
       payments {
           ...
               options {
                   2 {
                       provider = PAYPAL
                       title = PayPal
                       extra = 0.00
                       taxClassId = 1
                       status = open
                       available.from = 0.01
                   }
                   3 {
                       provider = PAYPAL_CREDITCARD
                       title = Credit card
                       extra = 0.00
                       taxClassId = 1
                       status = open
                       available.from = 0.01
                   }
               }
           ...
       }
   }

If Cart reads the payment methods from the site settings (site set loader of
Cart), the option is added to `extcode.cart-payments` in the same way:

.. code-block:: yaml
   :caption: config/sites/<site>/settings.yaml

   extcode:
     cart-payments:
       countries:
         de:
           options:
             2:
               provider: PAYPAL
               title: PayPal
               extra: 0.0
               taxClassId: 1
               status: open

|

.. container:: table-row

   Property
      plugin.tx_cart.payments....options.n.provider
   Data type
      string
   Description
      Lets Cart PayPal take over the payment of the order.

      * `PAYPAL`: The customer pays with the PayPal button and logs in at
        PayPal.
      * `PAYPAL_CREDITCARD`: The customer pays with a debit or credit card in
        an overlay of PayPal, a PayPal account is not required.

.. container:: table-row

   Property
      plugin.tx_cart.payments....options.n.preventClearCart
   Data type
      boolean
   Description
      Cart PayPal clears the cart before the customer pays. If the customer
      cancels the payment, the cart is restored. With `preventClearCart = 1`
      the cart is kept.

.. NOTE::

   PayPal decides whether the card payment is offered, e.g. depending on the
   country, the currency and the PayPal account of the shop. If it is not
   offered, the payment page shows a link to PayPal instead. Check that the
   payment with cards (standalone payments) is enabled in the settings of the
   PayPal app.

.. NOTE::

   For more information and examples on how to configure payment methods, please refer to the
   payment method section in the cart documentation.
