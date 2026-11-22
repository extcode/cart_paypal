<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout\Cart;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Model\Order\Item;
use Extcode\Cart\Domain\Model\Order\Payment;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\CartPaypal\Checkout\Payment\PaymentProvider;

/**
 * Finds the stored cart of an order, either by the hash the customer knows or
 * by the id of the PayPal order, which is stored as id of a transaction of the
 * payment.
 *
 * The stored cart holds the order and the cart; it is needed to dispatch the
 * events and to restore the cart if the customer cancels the payment.
 */
final class CartRecordFinder
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly PaymentRepository $paymentRepository,
    ) {}

    /**
     * The success hash identifies the order for the payment.
     */
    public function findBySHash(string $hash): ?Cart
    {
        return $this->findByHash('sHash', $hash);
    }

    /**
     * The failure hash identifies the order for the cancellation only.
     */
    public function findByFHash(string $hash): ?Cart
    {
        return $this->findByHash('fHash', $hash);
    }

    private function findByHash(string $property, string $hash): ?Cart
    {
        if ($hash === '') {
            return null;
        }

        $query = $this->cartRepository->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals($property, $hash));

        $cart = $query->execute()->getFirst();

        return $cart instanceof Cart ? $cart : null;
    }

    public function findByPayPalOrderId(string $payPalOrderId): ?Cart
    {
        if ($payPalOrderId === '') {
            return null;
        }

        $query = $this->paymentRepository->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching(
            $query->logicalAnd(
                $query->in('provider', PaymentProvider::values()),
                $query->equals('transactions.txnId', $payPalOrderId)
            )
        );

        $payment = $query->execute()->getFirst();
        if (!$payment instanceof Payment) {
            return null;
        }

        $orderItem = $payment->getItem();
        if (!$orderItem instanceof Item) {
            return null;
        }

        return $this->findByOrderItem($orderItem);
    }

    private function findByOrderItem(Item $orderItem): ?Cart
    {
        $query = $this->cartRepository->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('orderItem', $orderItem));

        $cart = $query->execute()->getFirst();

        return $cart instanceof Cart ? $cart : null;
    }
}
