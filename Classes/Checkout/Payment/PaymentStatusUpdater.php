<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Order\Payment;
use Extcode\Cart\OrderHistory\OrderChangeSource;
use Extcode\Cart\OrderStatus\StatusChangeOutcome;
use Extcode\Cart\OrderStatus\StatusChangeService;
use Psr\Log\LoggerInterface;

/**
 * Changes the status of a payment with the StatusChangeService of cart, which
 * changes it only if it has not been changed by another request in the
 * meantime. The change is stored in the order history.
 */
final class PaymentStatusUpdater
{
    public const INITIATOR = 'cart_paypal';

    public function __construct(
        private readonly StatusChangeService $statusChangeService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Returns true if this call changed the status. Only the request that
     * changed it may dispatch the events of the change.
     *
     * @param bool $byPayPal the change is caused by PayPal, e.g. a webhook, not by the customer
     */
    public function change(Payment $payment, string $toStatus, bool $byPayPal = false): bool
    {
        $source = $byPayPal ? OrderChangeSource::Api : null;

        $result = $this->statusChangeService->change(
            $payment,
            $toStatus,
            $payment->getStatus(),
            self::INITIATOR,
            null,
            $source
        );

        if ($result->outcome === StatusChangeOutcome::Stale && $result->storedStatus !== null) {
            // changed by another request since the payment was loaded, try again with the stored status
            $payment->setStatus($result->storedStatus);
            $result = $this->statusChangeService->change(
                $payment,
                $toStatus,
                $result->storedStatus,
                self::INITIATOR,
                null,
                $source
            );
        }

        if (!$result->isChanged() && $result->outcome !== StatusChangeOutcome::Unchanged) {
            $this->logger->error('The status of a payment paid with PayPal could not be changed.', [
                'payment' => $payment->getUid(),
                'toStatus' => $toStatus,
                'outcome' => $result->outcome->name,
                'vetoReason' => $result->vetoReason,
            ]);
        }

        return $result->isChanged();
    }
}
