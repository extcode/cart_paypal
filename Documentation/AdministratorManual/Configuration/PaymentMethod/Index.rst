.. include:: ../../../Includes.txt

Payment Method Configuration
============================

The payment method for PayPal is configured like any other payment method. There are all configuration options
from Cart available.

::

   plugin.tx_cart {
       payments {
           options {
               2 {
                   provider = PAYPAL
                   title = Paypal
                   extra = 0.00
                   taxClassId = 1
                   status = open
               }
               3 {
                   provider = PAYPAL_CREDIT_CARD
                   title = Credit Card
                   extra = 0.00
                   taxClassId = 1
                   status = open
               }
               4 {
                   provider = PAYPAL_CREDIT_CARD
                   title = Direct Debit
                   extra = 0.00
                   taxClassId = 1
                   status = open
               }
           }
       }
   }
|

.. container:: table-row

   Property
      plugin.tx_cart.payments.options.n.provider
   Data type
      string
   Description
      Defines that the payment provider for PayPal should be used. 
      This information is mandatory and ensures that the extension Cart PayPal takes control for the authorization
      of the payment and the user is forwarded to the PayPal site.
      The provider 'PAYPAL' forwards to the classic paypal page and a Paypal account is necessary.
      The provider 'PAYPAL_CREDIT_CARD' forwards to the billing page of Paypal allowing to pay with credit card and with direct debit.
      Generally the user can reach these options from the classic Paypal page as well but it's beneficial to provide the options directly in the shop and forward the user to his preferred option.
      However, take note that Cart Paypal cannot detect which payment option is finally used by the user - so please make sure to charge the same amount of fees for all Paypal payment options.
