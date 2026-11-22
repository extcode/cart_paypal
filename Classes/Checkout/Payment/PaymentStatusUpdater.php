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
use Psr\Log\LoggerInterface;

/**
 * Changes the status of a payment only if it has not been changed by another
 * request in the meantime.
 */
final class PaymentStatusUpdater
{
    public function __construct(
        private readonly PaymentStatusStore $paymentStatusStore,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Returns true if this call changed the status. Only the request that
     * changed it may dispatch the events of the change.
     *
     * @param bool $byPayPal the change is caused by PayPal, e.g. a webhook, not by the customer; not stored with cart 11
     */
    public function change(Payment $payment, string $toStatus, bool $byPayPal = false): bool
    {
        $changed = $this->paymentStatusStore->updateIfStatus($payment, $payment->getStatus(), $toStatus);

        if (!$changed) {
            // changed by another request since the payment was loaded, try again with the stored status
            $storedStatus = $this->paymentStatusStore->getStoredStatus($payment);
            $payment->setStatus($storedStatus);
            $changed = $this->paymentStatusStore->updateIfStatus($payment, $storedStatus, $toStatus);

            if (!$changed && $storedStatus !== $toStatus) {
                $this->logger->error('The status of a payment paid with PayPal could not be changed.', [
                    'payment' => $payment->getUid(),
                    'toStatus' => $toStatus,
                ]);
            }
        }

        if ($changed) {
            $payment->setStatus($toStatus);
        }

        return $changed;
    }
}
