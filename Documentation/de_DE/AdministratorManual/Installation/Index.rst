.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

Installation
============

Die Erweiterung wird wie jede andere Erweiterung im TYPO3 CMS installiert.

Extension Manager
-----------------

#. Wechsle in das Modul “Extension Manager”.

   #. **Get it from the Extension Manager:** Press the “Retrieve/Update”
      button and search for the extension key *cart_paypal* and import the
      extension from the repository.

   #. **Get it from typo3.org:** You can always get current version from
      `http://typo3.org/extensions/repository/view/cart_paypal/current/
      <http://typo3.org/extensions/repository/view/cart_paypal/current/>`_ by
      downloading either the t3x or zip version. Upload
      the file afterwards in the Extension Manager.

Versionsverwaltung (github)
---------------------------
Die aktuellste Version lässt sich über github mit den üblichen git-Kommandos herunterladen.

.. code-block:: bash

   git clone git@github.com:extcode/cart_paypal.git

|

Nachdem die Erweiterung heruntergeladen ist, kann sie über den Extension-Manager aktiviert werden.

Vorbereitung: Include static TypoScript
---------------------------------------

Die Erweiterung enthält eine TypoScript Konfigurationsdatei, die eingebunden werden muss. Diese
enthält allerdings nicht alle notwendigen Konfigurationen.

#. Switch to the root page of your site.

#. Switch to the **Template module** and select *Info/Modify*.

#. Press the link **Edit the whole template record** and switch to the tab *Includes*.

#. Select **Shopping Cart - PayPal (cart_paypal)** at the field *Include static (from extensions):*