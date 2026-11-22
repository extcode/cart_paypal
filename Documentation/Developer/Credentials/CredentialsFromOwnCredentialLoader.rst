.. include:: ../../Includes.rst.txt

.. _credentials-from-own-credentialloader:

=====================================
Credentials from own CredentialLoader
=====================================

If the credentials are to be saved in a different location, a separate configuration loader
can provide the configuration. It is sufficient if this implements the
`\Extcode\CartPaypal\Configuration\CredentialLoaderInterface`.
The loader is automatically registered via the interface.

The loaders are called in the order of their `getDefaultPriority()`, the
credentials of a loader with a higher priority take precedence. The
credentials from the site configuration have the priority 40, the credentials
from `$GLOBALS['TYPO3_CONF_VARS']` the priority 30.
