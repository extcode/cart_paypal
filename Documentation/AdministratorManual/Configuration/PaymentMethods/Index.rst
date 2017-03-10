.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

Bezahlmethode PayPal
====================

Die Bezahlmethode für PayPal wird wie jede andere Bezahlmethode konfiguriert. Es stehen alle Konfigurationsmöglichkeiten
aus Cart zur Verfügung.

::

   plugin.tx_cart {
       payments {
           options {
               2 {
                   provider = PAYPAL
                   title = Paypal
                   extra = 0.00
                   taxClassId = 1
                   status = open
                   preventBuyerEmail = 1
                   preventSellerEmail = 1
               }
           }
       }
   }

|

.. container:: table-row

   Property
      payments.options.n.provider
   Data type
      string
   Description
      Definiert, dass der PaymentProvider Paypal genutzt werden soll. Diese Angabe ist Plicht und sorgt dafür, das die Erweiterung CartPaypal die Kontrolle übernimmt und für die Authorisierung der Bezahlung den Nutzer auf die PayPal-Seite weiterleitet.
