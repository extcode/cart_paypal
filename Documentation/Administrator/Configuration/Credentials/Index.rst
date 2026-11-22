.. include:: ../../../Includes.rst.txt

.. _credentials:

===========
Credentials
===========

Cart PayPal needs the credentials of a PayPal app (REST API).

========================  ===================================================
Credential                Description
========================  ===================================================
clientId                  The client id of the app. It is public, the
                          JavaScript SDK on the payment page uses it.
clientSecret              The secret of the app.
webhookId                 The id of the webhook of the app, see
                          :ref:`Webhook <webhook>`. Without it, webhooks are
                          rejected.
sandbox                   `1` uses the PayPal sandbox, `0` the live system.
                          Without the setting the sandbox is used.
========================  ===================================================

Store the credentials in environment variables and read them in
`config/system/additional.php`, see
:ref:`Credentials from $GLOBALS['TYPO3_CONF_VARS'] <credentials-from-confvars>`.
This is the recommended way.

Use the :ref:`credentials from the site configuration <credentials-from-site-configuration>`
only if the sites of an installation need different PayPal apps.

.. IMPORTANT::

   The credentials are no site settings and no TypoScript. Site settings are
   shown in the backend module *Site Settings* and in the TypoScript of the
   site, and saving them in the backend writes the values of environment
   variables into `settings.yaml`. TypoScript is versioned and deployed with
   the project or stored in the database.

If the credentials are to be saved in a different way,
:ref:`Credentials from own CredentialLoader <credentials-from-own-credentialloader>`
describes how to provide the credentials implementing the
`CredentialLoaderInterface`.

-----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Credentials from TYPO3_CONF_VARS <credentials-from-confvars>`

        Credentials are read from $GLOBALS['TYPO3_CONF_VARS'], set from
        environment variables in `config/system/additional.php`.

    ..  card:: :ref:`Credentials from the site configuration <credentials-from-site-configuration>`

        Credentials are read from the configuration of the site, for
        installations with different PayPal apps per site.

.. toctree::
   :maxdepth: 2
   :titlesonly:

   CredentialsFromConfVars
   CredentialsFromSiteConfiguration
