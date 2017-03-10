.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

E-Mail-Konfiguration
====================

Da die Payment Provider Extension (hier: CartPaypal) nicht über das Frontend-Plugin arbeitet und auch an keiner Stelle gespeichert wird,
über welches Frontend-Plugin die Bestellung erfolgte, kann der MailHandler nicht auf die im Plugin konfigurierten E-Mail-Adressen zurückgreifen und verwenden.
Aus diesem Grund gilt:

.. IMPORTANT::
   Damit die Payment Provider Extension E-Mails versenden kann, müssen die E-Mail-Adressen unbedingt per TypoScript konfiguriert werden.


`E-Mail-Konfiguration in der Cart Dokumentation <https://docs.typo3.org/typo3cms/extensions/cart/AdministratorManual/Configuration/MailConfiguration/Index.html>`__
