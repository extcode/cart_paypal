<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Model\Cart\Cart as CartCart;
use Extcode\Cart\Domain\Model\Order\Item;
use Extcode\Cart\Domain\Model\Order\Payment;
use Extcode\Cart\Domain\Model\Order\Transaction;
use Extcode\CartPaypal\Checkout\CheckoutException;

/**
 * An order paid with PayPal: the stored cart, its order and the payment.
 *
 * Created once per request from the stored cart, which is checked to belong
 * to an order paid with PayPal.
 */
final readonly class PayPalPayment
{
    private function __construct(
        private Cart $cartRecord,
        private Item $orderItem,
        private Payment $payment,
        private PaymentProvider $provider,
    ) {}

    /**
     * @throws CheckoutException if the stored cart has no order paid with PayPal
     */
    public static function fromCartRecord(Cart $cartRecord): self
    {
        $orderItem = $cartRecord->getOrderItem();
        if (!$orderItem instanceof Item) {
            throw new CheckoutException('The stored cart has no order.', CheckoutException::NO_ORDER);
        }

        $payment = $orderItem->getPayment();
        $provider = PaymentProvider::tryFrom((string)$payment?->getProvider());
        if (!$payment instanceof Payment || !$provider instanceof PaymentProvider) {
            throw new CheckoutException('The order is not paid with PayPal.', CheckoutException::NOT_PAYPAL);
        }

        return new self($cartRecord, $orderItem, $payment, $provider);
    }

    public function getCartRecord(): Cart
    {
        return $this->cartRecord;
    }

    /**
     * The success hash of the stored cart, the customer pays the order with it.
     */
    public function getSHash(): string
    {
        return $this->cartRecord->getSHash();
    }

    /**
     * The failure hash of the stored cart, the customer cancels the payment
     * with it.
     */
    public function getFHash(): string
    {
        return $this->cartRecord->getFHash();
    }

    public function getOrderItem(): Item
    {
        return $this->orderItem;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getStatus(): string
    {
        return $this->payment->getStatus();
    }

    public function isPaid(): bool
    {
        return $this->payment->isPaid();
    }

    /**
     * A pending payment is captured by PayPal, but the money has not been
     * received yet, e.g. because PayPal reviews it.
     */
    public function isPending(): bool
    {
        return $this->payment->isPending();
    }

    /**
     * A paid or pending payment must not be paid again.
     */
    public function isPaidOrPending(): bool
    {
        return $this->isPaid() || $this->isPending();
    }

    public function isCanceled(): bool
    {
        return $this->payment->isCanceled();
    }

    public function isCreditCard(): bool
    {
        return $this->provider === PaymentProvider::CreditCard;
    }

    /**
     * The cart of the order, the events of cart need it.
     *
     * @throws CheckoutException
     */
    public function getCart(): CartCart
    {
        $cart = $this->cartRecord->getCart();
        if (!$cart instanceof CartCart) {
            throw new CheckoutException('The stored cart has no cart.', CheckoutException::NO_ORDER);
        }

        return $cart;
    }

    /**
     * The transaction of a PayPal order created for this payment.
     *
     * @throws CheckoutException if the PayPal order does not belong to this payment
     */
    public function getTransaction(string $payPalOrderId): Transaction
    {
        foreach ($this->payment->getTransactions() as $transaction) {
            if ($payPalOrderId !== '' && $transaction->getTxnId() === $payPalOrderId) {
                return $transaction;
            }
        }

        throw new CheckoutException('The PayPal order does not belong to the order.', CheckoutException::UNKNOWN_PAYPAL_ORDER);
    }
}
