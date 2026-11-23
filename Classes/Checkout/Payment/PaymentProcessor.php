<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Order\Item;
use Extcode\Cart\Domain\Order\Status\PaymentStatus;
use Extcode\CartPaypal\Api\ApiException;
use Extcode\CartPaypal\Api\PayPalClient;
use Extcode\CartPaypal\Checkout\CheckoutException;
use Extcode\CartPaypal\Checkout\Settings;
use Extcode\CartPaypal\Checkout\UrlBuilder;
use Extcode\CartPaypal\Event\Order\CancelEvent;
use Extcode\CartPaypal\Event\Order\FinishEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Creates and captures the orders at PayPal and updates the payment of the
 * order in cart.
 *
 * Each PayPal order is stored as transaction of the payment, the id of the
 * PayPal order is the transaction id. The FinishEvent is dispatched only by
 * the request that changed the status to paid, even if the JavaScript, the
 * return URL and the webhook capture the same order at the same time.
 */
final class PaymentProcessor
{
    public function __construct(
        private readonly PayPalClient $client,
        private readonly OrderRequestBuilder $orderRequestBuilder,
        private readonly UrlBuilder $urlBuilder,
        private readonly Settings $settings,
        private readonly PaymentStatusUpdater $paymentStatusUpdater,
        private readonly TransactionWriter $transactionWriter,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Creates the order at PayPal. For the redirect to PayPal and for the
     * PayPal button the order returns the customer to the shop, for the card
     * button of the JavaScript SDK it has no payment source.
     *
     * @throws CheckoutException
     * @throws ApiException
     */
    public function createPayPalOrder(PayPalPayment $payPalPayment, bool $forRedirect = false): PayPalOrder
    {
        if ($payPalPayment->isPaidOrPending()) {
            throw new CheckoutException('The order is paid or its payment is pending.', CheckoutException::PAID_OR_PENDING);
        }
        if ($payPalPayment->isCanceled()) {
            throw new CheckoutException('The payment of the order was canceled.', CheckoutException::CANCELED);
        }

        $orderItem = $payPalPayment->getOrderItem();
        if (!$forRedirect && $payPalPayment->isCreditCard()) {
            $orderRequest = $this->orderRequestBuilder->buildForCard($orderItem);
        } else {
            $orderRequest = $this->orderRequestBuilder->build(
                $orderItem,
                $this->urlBuilder->getApiUrl('return', $payPalPayment->getSHash()),
                $this->urlBuilder->getApiUrl('cancel', $payPalPayment->getFHash())
            );
        }
        $order = $this->client->createOrder($orderRequest);

        $payPalOrderId = $this->getPayPalOrderId($order);
        $this->transactionWriter->addPayPalOrder(
            $payPalPayment->getPayment(),
            $payPalOrderId,
            (string)($order['status'] ?? '')
        );

        return new PayPalOrder($payPalOrderId, $this->getApproveUrl($order));
    }

    /**
     * Captures the PayPal order after the customer approved it.
     *
     * @param bool $byPayPal the capture is caused by PayPal, e.g. a webhook, not by the customer
     *
     * @throws CheckoutException if the PayPal order does not belong to the order
     * @throws ApiException
     */
    public function capture(PayPalPayment $payPalPayment, string $payPalOrderId, bool $byPayPal = false): CaptureResult
    {
        $transaction = $payPalPayment->getTransaction($payPalOrderId);

        if ($payPalPayment->isPaid()) {
            return CaptureResult::Paid;
        }

        try {
            $order = $this->client->captureOrder($payPalOrderId);
        } catch (ApiException $exception) {
            if ($exception->getIssue() === 'ORDER_ALREADY_CAPTURED') {
                $order = $this->client->getOrder($payPalOrderId);
            } elseif ($exception->getStatusCode() === 422) {
                // e.g. INSTRUMENT_DECLINED, the customer may try again
                $this->logger->warning('PayPal declined the capture of an order.', [
                    'payPalOrderId' => $payPalOrderId,
                    'issue' => $exception->getIssue(),
                    'debugId' => $exception->getDebugId(),
                ]);
                $this->transactionWriter->changeStatus($transaction, TransactionStatus::Failed, $exception->getIssue() ?: $exception->getName());

                return CaptureResult::Failed;
            } else {
                throw $exception;
            }
        }

        $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? null;
        if (!is_array($capture)) {
            $this->transactionWriter->changeStatus($transaction, TransactionStatus::Failed, (string)($order['status'] ?? ''));

            return CaptureResult::Failed;
        }

        return $this->applyCapture($payPalPayment, $payPalOrderId, $capture, $byPayPal);
    }

    /**
     * Updates the payment with a capture, as returned by the capture or sent
     * by a webhook.
     *
     * @param array $capture a capture of the Orders v2 API
     * @param bool $byPayPal the capture is reported by PayPal, e.g. a webhook, not by the customer
     *
     * @throws CheckoutException if the PayPal order does not belong to the order
     */
    public function applyCapture(PayPalPayment $payPalPayment, string $payPalOrderId, array $capture, bool $byPayPal): CaptureResult
    {
        $transaction = $payPalPayment->getTransaction($payPalOrderId);
        $orderItem = $payPalPayment->getOrderItem();
        $payment = $payPalPayment->getPayment();
        $status = (string)($capture['status'] ?? '');
        $captureId = (string)($capture['id'] ?? '');

        if ($status === 'COMPLETED') {
            if (!$this->isAmountOfOrder($orderItem, $capture['amount'] ?? null)) {
                $this->logger->error('The amount captured by PayPal differs from the order, the payment has to be checked.', [
                    'orderItem' => $orderItem->getUid(),
                    'captureId' => $captureId,
                ]);
                $this->transactionWriter->changeStatus($transaction, TransactionStatus::Pending, $status, $captureId);
                $this->paymentStatusUpdater->change($payment, PaymentStatus::PENDING, $byPayPal);

                return CaptureResult::Pending;
            }

            $this->transactionWriter->changeStatus($transaction, TransactionStatus::Paid, $status, $captureId);
            if ($this->paymentStatusUpdater->change($payment, PaymentStatus::PAID, $byPayPal)) {
                $this->eventDispatcher->dispatch(
                    new FinishEvent($payPalPayment->getCart(), $orderItem, $this->settings->getCartConfiguration())
                );
            }

            return CaptureResult::Paid;
        }

        if ($payPalPayment->isPaid()) {
            // a webhook sent out of order must not change a paid order
            return CaptureResult::Paid;
        }

        if ($status === 'PENDING') {
            $this->transactionWriter->changeStatus($transaction, TransactionStatus::Pending, $status, $captureId);
            $this->paymentStatusUpdater->change($payment, PaymentStatus::PENDING, $byPayPal);

            return CaptureResult::Pending;
        }

        $this->transactionWriter->changeStatus($transaction, TransactionStatus::Failed, $status, $captureId);
        if ($payPalPayment->isPending()) {
            $this->paymentStatusUpdater->change($payment, PaymentStatus::CANCELED, $byPayPal);
        }

        return CaptureResult::Failed;
    }

    /**
     * Cancels the payment when the customer returns to the cart. Returns false
     * if the order is paid already and can not be canceled.
     */
    public function cancel(PayPalPayment $payPalPayment): bool
    {
        if ($payPalPayment->isPaidOrPending()) {
            return false;
        }

        $this->transactionWriter->cancelOpenPayPalOrders($payPalPayment->getPayment());

        if ($this->paymentStatusUpdater->change($payPalPayment->getPayment(), PaymentStatus::CANCELED)) {
            $this->eventDispatcher->dispatch(
                new CancelEvent($payPalPayment->getCart(), $payPalPayment->getOrderItem(), $this->settings->getCartConfiguration())
            );
        }

        return true;
    }

    private function isAmountOfOrder(Item $orderItem, mixed $amount): bool
    {
        if (!is_array($amount)) {
            return false;
        }

        $currencyCode = strtoupper($orderItem->getCurrencyCode());

        return strtoupper((string)($amount['currency_code'] ?? '')) === $currencyCode
            && OrderRequestBuilder::formatAmount((float)($amount['value'] ?? 0), $currencyCode)
                === OrderRequestBuilder::formatAmount($orderItem->getTotalGross(), $currencyCode);
    }

    /**
     * @throws ApiException
     */
    private function getPayPalOrderId(array $order): string
    {
        $payPalOrderId = (string)($order['id'] ?? '');
        if ($payPalOrderId === '') {
            throw new ApiException('PayPal did not return the id of the order.', 1795600005);
        }

        return $payPalOrderId;
    }

    private function getApproveUrl(array $order): string
    {
        foreach ($order['links'] ?? [] as $link) {
            if (in_array($link['rel'] ?? '', ['payer-action', 'approve'], true)) {
                return (string)($link['href'] ?? '');
            }
        }

        return '';
    }
}
