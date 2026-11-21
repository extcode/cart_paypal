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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Reads and updates the status of a payment in the database, bypassing the
 * persistence of Extbase.
 *
 * cart 11 has no StatusChangeService, the status is updated with a
 * conditional query.
 */
final class PaymentStatusStore
{
    private const PAYMENT_TABLE = 'tx_cart_domain_model_order_payment';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Updates the status only if the payment still has the expected status
     * ($fromStatus). Of several requests with the same expected status only
     * one changes it.
     *
     * Returns true if this call changed the status.
     */
    public function updateIfStatus(Payment $payment, string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return false;
        }

        $affectedRows = $this->connectionPool
            ->getConnectionForTable(self::PAYMENT_TABLE)
            ->update(
                self::PAYMENT_TABLE,
                [
                    'status' => $toStatus,
                    'tstamp' => time(),
                ],
                [
                    'uid' => (int)$payment->getUid(),
                    'status' => $fromStatus,
                ],
                [
                    'tstamp' => Connection::PARAM_INT,
                    'uid' => Connection::PARAM_INT,
                ]
            );

        return $affectedRows > 0;
    }

    public function getStoredStatus(Payment $payment): string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::PAYMENT_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return (string)$queryBuilder
            ->select('status')
            ->from(self::PAYMENT_TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int)$payment->getUid(), Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }
}
