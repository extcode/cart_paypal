.. include:: ../../../Includes.rst.txt

.. _payment-page:

============
Payment page
============

The payment page shows the PayPal button, or the card button for the payment
method `PAYPAL_CREDITCARD`. The customer pays in a window of PayPal without
leaving the shop.

#. Create a page, e.g. "Payment", and hide it in the menu.

#. Add the content element **Cart: PayPal payment** to the page.

#. Select the page in the site setting "Payment page" or set the constant
   `plugin.tx_cartpaypal.paymentPid` to the uid of the page.

The page shows the order with its products, costs and addresses next to the
PayPal button. The template `Payment/Show.html` and the partials
`Payment/OrderSummary.html` and `Payment/Addresses.html` can be overridden
like the templates of other Extbase plugins (`plugin.tx_cartpaypal.view`).
The amounts are formatted with the currency format of Cart. The element with the attribute
`data-cart-paypal-payment` and its data attributes are needed by the
JavaScript `EXT:cart_paypal/Resources/Public/JavaScript/Payment.js`.

If PayPal can not be offered by the JavaScript SDK, e.g. for the currency, the
page shows a link which redirects the customer to PayPal instead.

.. IMPORTANT::

   With a Content Security Policy for the frontend, allow the PayPal JavaScript
   SDK (`https://www.paypal.com`, `https://www.sandbox.paypal.com`) for
   scripts, frames and connections.
