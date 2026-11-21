.. include:: ../../Includes.rst.txt

.. _installation:

============
Installation
============

Installation using Composer
---------------------------

The recommended way to install the extension is by using `Composer <https://getcomposer.org/>`_.
In your Composer based TYPO3 project root, just do

`composer require extcode/cart-paypal`.

Installation from TYPO3 Extension Repository (TER)
--------------------------------------------------

Download and install the extension with the extension manager module.

Preparation: Add the site set or include the static TypoScript
--------------------------------------------------------------

With site sets, add the set **Cart PayPal** (`extcode/cart-paypal`) to the
site, either in the backend module *Sites* or in the `config.yaml` of the site:

.. code-block:: yaml

   dependencies:
     - extcode/cart-paypal

The settings of the set can then be edited in the backend module *Site
Settings*, see :ref:`Main Configuration <main-configuration>`.

.. NOTE::

   If the site has a TypoScript record (`sys_template`) as well, its flags
   "Clear constants" and "Clear setup" must not be set, otherwise the
   TypoScript of the set is discarded.

Without site sets, include the static TypoScript:

#. Switch to the root page of your site.

#. Switch to the **Template module** and select *Info/Modify*.

#. Press the link **Edit the whole template record** and switch to the tab *Includes*.

#. Select **Shopping Cart - PayPal** at the field *Include static (from extensions):*

Preparation: PayPal app
-----------------------

#. Create an app in the `PayPal Developer Dashboard <https://developer.paypal.com/dashboard/applications>`_,
   one for the sandbox and one for live.

#. Configure the client id and the secret of the app as
   :ref:`credentials <credentials>`.

#. Add a webhook to the app, see :ref:`Webhook <webhook>`, and configure its id
   as credential as well.
