.. include:: ../../../Includes.txt

Main Configuration
==================

The plugin needs to know the merchant e-mail address.

::

   plugin.tx_cartpaypal {
       sandbox = 1
       business = pp-wt-merchant@extco.de
   }

|

.. container:: table-row

   Property
         plugin.tx_cartpaypal.sandbox
   Data type
         boolean
   Description
         This configuration determines whether the extension is in live or in sandbox mode.
   Default
         The default value is chosen so that the plugin is always in sandbox mode after installation, so that payment can be tested with PayPal.

.. container:: table-row

   Property
         plugin.tx_cartpaypal.business
   Data type
         boolean
   Description
         The e-mail address stored in the PayPal business account must be entered here.
