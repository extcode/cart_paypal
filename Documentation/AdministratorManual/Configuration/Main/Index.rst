.. include:: ../../../Includes.txt

Main Configuration
==================

.. IMPORTANT::
   The EventListeners are configured for the new Feature "Split Up ProcessOrderCreateEvent" in extcode/cart v7.2.0.
   The feature must be switched on and, if necessary, own event listeners must be adapted to these new events.
   For more information, please read the cart documentation:

   * `Feature-337-SplitUpProcessOrderCreateEvent <https://docs.typo3.org/p/extcode/cart/master/en-us/Changelog/7.2/Feature-337-SplitUpProcessOrderCreateEvent.html>`__
   * `Deprecation-337-SplitUpProcessOrderCreateEvent <https://docs.typo3.org/p/extcode/cart/master/en-us/Changelog/7.2/Deprecation-337-SplitUpProcessOrderCreateEvent.html>`__

The plugin needs to know the merchant e-mail address.

::

   plugin.tx_cartpaypal {
       sandbox = 1
       business = pp-wt-merchant@extco.de
       debug = 1
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
         string
   Description
         The e-mail address stored in the PayPal business account must be entered here.

.. container:: table-row

   Property
         plugin.tx_cartpaypal.debug
   Data type
         boolean
   Description
         Enables the logging for some debug messages.