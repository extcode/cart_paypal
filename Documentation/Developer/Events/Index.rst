.. include:: ../../Includes.rst.txt

.. _events:

======
Events
======

Cart PayPal stops the `\Extcode\Cart\Event\Order\PaymentEvent` of Cart, so the
`\Extcode\Cart\Event\Order\FinishEvent` of Cart is not dispatched. Instead
Cart PayPal dispatches its own events after the payment.

`\Extcode\CartPaypal\Event\Order\FinishEvent`
   Dispatched once when the payment is completed. Cart PayPal registers the
   email listener of Cart for it.

`\Extcode\CartPaypal\Event\Order\CancelEvent`
   Dispatched once when the customer cancels the payment and returns to the
   cart.

The status of the payment is changed with the `StatusChangeService` of Cart,
its `BeforeStatusChangeEvent` and `AfterStatusChangeEvent` are dispatched as
well, with the initiator `cart_paypal`.
