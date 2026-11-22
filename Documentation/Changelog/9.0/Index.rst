.. include:: ../../Includes.rst.txt

===========
9.0 Changes
===========

The version 9.x is a release for TYPO3 v14.3 and extcode/cart 12.x. It
provides the features of version 8.x.

Features
========

* The status of the payment is changed with the `StatusChangeService` of Cart.
  The changes are stored in the order history with the initiator
  `cart_paypal`, the `BeforeStatusChangeEvent` and `AfterStatusChangeEvent` of
  Cart are dispatched.
