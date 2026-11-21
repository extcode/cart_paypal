.. include:: ../../../Includes.rst.txt

.. _webhook:

=======
Webhook
=======

PayPal informs the shop about payments with webhooks. They finish the order if
the customer closes the browser after approving the payment, and if PayPal
completes a reviewed payment later.

#. Add a webhook to the PayPal app with the URL of the page type on the cart
   page, e.g. `https://example.org/cart?type=2278101`.

#. Select the events

   * `CHECKOUT.ORDER.APPROVED`
   * `PAYMENT.CAPTURE.COMPLETED`
   * `PAYMENT.CAPTURE.PENDING`
   * `PAYMENT.CAPTURE.DENIED`
   * `PAYMENT.CAPTURE.DECLINED`

#. Configure the id of the webhook as credential `webhookId`.

The signature of each webhook is verified by PayPal. Webhooks of orders the
shop does not know, e.g. of another shop with the same PayPal account, are
acknowledged and ignored.
