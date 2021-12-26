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
        displayShippingAddress = 1
        logo = fileadmin/cart/somelogo.png
        regionMappings {
            at = at,AT
            de = de,DE
            ch = ch,CH
            be = be,BE
            fr = fr,FR
            it = it,IT
            lu = lu,LU
            nl = nl,NL
            sk = sk,SK
            si = si,SI
        }
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
         
.. container:: table-row

   Property
         plugin.tx_cartpaypal.displayShippingAddress
   Data type
         boolean
   Description
         Defines if Paypal shall display the shipping address form fields or not (Paypal variable no_shipping).

.. container:: table-row

   Property
         plugin.tx_cartpaypal.logo
   Data type
         string
   Description
         Defines the path to a logo being displayed by Paypal. Supported formats are GIF, JPG, PNG with a max of 50KB and dimensions of max 750px width and 90px height.

.. container:: table-row

   Property
         plugin.tx_cartpaypal.regionMappings
   Data type
         array
   Description
         Defines the country code and locale being pre-selected in Paypal according to the user-selected target shipment country
         (e.g. user selects the Netherlands as target shipment country it makes sense to already pre-select nl,NL as country code and locale)
         Left hand is the list of allowed countries as defined for tx_cart (plugin.tx_cart.settings.allowedCountries), right hand side is the combination of country code and locale as defined by Paypal)
         Example: en = en,GB --> country code = en, locale = GB
         In case no related regionMapping can be found, default bahvior of Paypal is used.
