.. include:: ../../../Includes.rst.txt

.. _credentials-from-site-configuration:

=======================================
Credentials from the site configuration
=======================================

If the sites of an installation use different PayPal apps, the credentials
can be set per site in the site configuration
`config/sites/<identifier>/config.yaml`. Use them only if different
credentials are needed, otherwise use the
:ref:`credentials from $GLOBALS['TYPO3_CONF_VARS'] <credentials-from-confvars>`.

The site configuration is usually versioned. Read the values from environment
variables with the placeholder `%env()%` of TYPO3, never write a secret into
the file. Placeholders are kept when the site is saved in the backend module
*Sites*.

.. code-block:: yaml
   :caption: config/sites/shop/config.yaml

   cartPaypal:
     credentials:
       clientId: '%env(SHOP_PAYPAL_CLIENT_ID)%'
       clientSecret: '%env(SHOP_PAYPAL_CLIENT_SECRET)%'
       webhookId: '%env(SHOP_PAYPAL_WEBHOOK_ID)%'
       sandbox: '%env(SHOP_PAYPAL_SANDBOX)%'

The credentials of the site take precedence over the credentials of
`$GLOBALS['TYPO3_CONF_VARS']`, credentials missing in the site are taken from
there. Set all credentials in the site to avoid mixing two PayPal apps.

See :ref:`Environment variables <credentials-environment-variables>` for how to
provide the environment variables.

.. IMPORTANT::

   Do not use the file `settings.yaml` of the site (site settings). Saving the
   site settings in the backend replaces the placeholders with the values of
   the environment variables.
