.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

PayPal-Konfiguration
====================

Das Plugin braucht einige Angaben, die an PayPal übermittelt werden, damit PayPal den Händler kennt und auch
Rücksprungadressen in den Shop kennt.

::

   plugin.tx_cartpaypal {
       settings {
           business = pp-wt-merchant@extco.de

           notify_url = https://cart-dev.extco.de/?eID=processIpn
           return_url = https://cart-dev.extco.de/
           cancel_url = https://cart-dev.extco.de/warenkorb
       }
   }

|

.. container:: table-row

   Property
         settings.sandbox
   Data type
         boolean
   Description
         Diese Konfiguration legt fest, ob sich die Erweiterung im Live- oder im Sandbox-Modus befindet. Der Standardwert ist so gewählt, dass das Plugin nach der Installation immer im Sandbox-Modus ist, so dass das Bezahlen mit PayPal getestet werden kann.

.. container:: table-row

   Property
         settings.business
   Data type
         boolean
   Description
         Es muss die im PayPal-Geschäftskundenkonto hinterlegte E-Mail-Adresse eingetragen werden. Diese wird bei der Weiterleitung zu PayPal an PayPal übertragen.

.. container:: table-row

   Property
         settings.notify_url
   Data type
         boolean
   Description
         Diese Konfiguration setzt sich aus einer Domain, unter der der Shop erreichbar ist und dem eID-Parameter zusammen. Dieser Parameter ist immer gleich.

.. container:: table-row

   Property
         settings.return_url
   Data type
         boolean
   Description
         Diese return_url wird mit den Informationen zur Bestellung an PayPal übertragen. PayPal blendet den Link an verschiedenen Stellen ein, damit der Nutzer nach der Bezahlung über PayPal wieder auf die Webseite geleitet werden kann.

.. container:: table-row

   Property
         settings.cancel_url
   Data type
         boolean
   Description
         Diese cancel_url wird mit den Informationen zur Bestellung an PayPal übertragen. PayPal blendet den Link an verschiedenen Stellen ein, damit der Nutzer das Bezahlung mit PayPal abbrechen kann und wieder auf die Webseite kommt.

.. IMPORTANT::
   Die Session (mit dem Warenkorb) wird derzeit vor der Weiterleitung zu PayPal gelöscht, so dass ein Nutzer nach dem er von PayPal auf die Webseite zurückgeleitet wird, einen leeren Warenkorb vorfindet. Auch in dem Fall, dass das Bezahlen abgebrochen wird. Eine Lösung ist in Planung.
