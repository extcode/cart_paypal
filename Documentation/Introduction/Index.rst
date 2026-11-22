.. include:: ../Includes.rst.txt

.. _introduction:

============
Introduction
============

Cart is a small but powerful extension which "solely" adds a shopping cart to your TYPO3 installation.
Cart PayPal is a payment provider and implements the payment with PayPal.

How the payment works
=====================

#. The customer orders with the payment method PayPal. Cart stores the order,
   Cart PayPal stores the cart and sends the customer to the payment page.
#. The payment page shows the PayPal button of the PayPal JavaScript SDK, or
   the card button if the customer chose to pay by card. When the customer
   clicks it, the shop creates the order at PayPal with the total
   of the stored order. The browser never sends an amount.
#. The customer approves the payment in the PayPal window. The shop captures
   the payment and shows the order confirmation of Cart.
#. A webhook of PayPal finishes the order if the customer closes the browser
   after approving the payment, and reports payments which are reviewed by
   PayPal first.

Without a payment page, or if the JavaScript can not be used, the customer is
redirected to PayPal and returns to the shop after approving the payment.

Each order at PayPal is stored as transaction of the payment of the order. The
id of the PayPal order is the transaction id, the id of the capture is stored
as note. No further database fields are needed.

The emails of Cart are sent after the payment is completed.

.. toctree::
   :maxdepth: 2
   :titlesonly:

   SupportedVersions/Index
   Support/Index
   Sponsoring/Index
