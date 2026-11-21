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
use Extcode\Cart\Domain\Model\Order\Transaction;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\Domain\Repository\Order\TransactionRepository;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Stores the PayPal orders as transactions of the payment and persists each
 * change immediately, so a parallel request (JavaScript, return URL,
 * webhook) finds it.
 */
final class TransactionWriter
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
    ) {}

    /**
     * Stores a PayPal order created for the payment, the id of the PayPal
     * order is the transaction id.
     */
    public function addPayPalOrder(Payment $payment, string $payPalOrderId, string $externalStatusCode): void
    {
        $transaction = new Transaction();
        $transaction->setPid((int)$payment->getPid());
        $transaction->setTxnId($payPalOrderId);
        $transaction->setStatus(TransactionStatus::Created->value);
        $transaction->setExternalStatusCode($externalStatusCode);

        $payment->addTransaction($transaction);
        $this->paymentRepository->update($payment);
        $this->persistenceManager->persistAll();
    }

    /**
     * @param string $captureId the id of the capture at PayPal, stored as note
     */
    public function changeStatus(
        Transaction $transaction,
        TransactionStatus $status,
        string $externalStatusCode,
        string $captureId = ''
    ): void {
        $transaction->setStatus($status->value);
        $transaction->setExternalStatusCode($externalStatusCode);
        if ($captureId !== '') {
            $transaction->setNote($captureId);
        }

        $this->transactionRepository->update($transaction);
        $this->persistenceManager->persistAll();
    }

    /**
     * Cancels the PayPal orders of the payment which have not been captured.
     */
    public function cancelOpenPayPalOrders(Payment $payment): void
    {
        foreach ($payment->getTransactions() as $transaction) {
            if ($transaction->getStatus() === TransactionStatus::Created->value) {
                $transaction->setStatus(TransactionStatus::Canceled->value);
                $this->transactionRepository->update($transaction);
            }
        }

        $this->persistenceManager->persistAll();
    }
}
