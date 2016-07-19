.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

Einführung
----------

CartPaypal fügt PayPal als Zahlungsanbieter (Payment Provider) zu Cart. Wählt ein Käufer diese Option aus, wird er mit der Bestellung zu PayPal weitergeleitet und gibt dort mit den Zugangsdaten die Zahlung frei. Anschließend kommt der Käufer auf die TYPO3-Seite zurück.

PayPal kommuniziert dann über die IPN-Schnittstelle und ändert den Status der Zahlung, so dass der Status der Bestellung im TYPO3-Backend ersichtlich ist.

Die Cart Erweiterung übernimmt dann wieder den E-Mail-Versand.

.. toctree::
   :maxdepth: 5
   :titlesonly:

   DevelopmentTeam/Index
   SupportersAndSponsors/Index
   Support/Index
