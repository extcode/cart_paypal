.. include:: ../../../Includes.rst.txt

.. _credentials-from-confvars:

============================================
Credentials from $GLOBALS['TYPO3_CONF_VARS']
============================================

The credentials are set in `config/system/additional.php` from environment
variables. The file contains no secret and can be versioned with the project.

.. code-block:: php
   :caption: config/system/additional.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['cart_paypal'] = [
       'credentials' => [
           'clientId' => getenv('PAYPAL_CLIENT_ID'),
           'clientSecret' => getenv('PAYPAL_CLIENT_SECRET'),
           'webhookId' => getenv('PAYPAL_WEBHOOK_ID'),
           'sandbox' => getenv('PAYPAL_SANDBOX'),
       ],
   ];

The names of the environment variables are free to choose.

.. _credentials-environment-variables:

Environment variables
=====================

TYPO3 does not read a `.env` file itself. The environment variables have to
be provided by one of these:

* the web server or PHP-FPM, e.g. `SetEnv` of Apache or `env[...]` in the pool
  configuration of PHP-FPM,
* the container, e.g. `environment` or `env_file` in a Docker Compose file,
* the hosting environment, many hosters allow to define environment variables,
* a package reading a `.env` file, e.g. `helhum/dotenv-connector`.

Environment variables of the web server are not available on the command
line. Provide them there as well if a command needs the credentials.

.. WARNING::

   Do not put the credentials into `config/system/settings.php`. The file is
   written by the backend and the install tool and often versioned.

Never commit the `.env` file with the credentials, add it to `.gitignore`.
