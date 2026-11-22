<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout\Webhook;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\CartPaypal\Api\ApiException;
use Extcode\CartPaypal\Checkout\Cart\CartRecordFinder;
use Extcode\CartPaypal\Checkout\CheckoutException;
use Extcode\CartPaypal\Checkout\Payment\PaymentProcessor;
use Extcode\CartPaypal\Checkout\Payment\PayPalPayment;
use Psr\Log\LoggerInterface;

/**
 * Processes the webhooks of PayPal, their signature has to be verified
 * before.
 */
final class WebhookHandler
{
    public function __construct(
        private readonly CartRecordFinder $cartRecordFinder,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws CheckoutException if the webhook does not fit the order
     * @throws ApiException
     */
    public function handle(array $event): void
    {
        $eventType = WebhookEventType::tryFrom((string)($event['event_type'] ?? ''));
        $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];

        if (!$eventType instanceof WebhookEventType) {
            $this->logger->info('Ignored a webhook of PayPal.', ['eventType' => (string)($event['event_type'] ?? '')]);
            return;
        }

        $payPalOrderId = match ($eventType) {
            WebhookEventType::OrderApproved => (string)($resource['id'] ?? ''),
            default => (string)($resource['supplementary_data']['related_ids']['order_id'] ?? ''),
        };

        $payPalPayment = $this->findPayPalPayment($eventType, $payPalOrderId);
        if (!$payPalPayment instanceof PayPalPayment) {
            return;
        }

        match ($eventType) {
            WebhookEventType::OrderApproved => $this->paymentProcessor->capture($payPalPayment, $payPalOrderId, true),
            WebhookEventType::CaptureCompleted,
            WebhookEventType::CapturePending,
            WebhookEventType::CaptureDenied,
            WebhookEventType::CaptureDeclined => $this->paymentProcessor->applyCapture($payPalPayment, $payPalOrderId, $resource, true),
        };
    }

    private function findPayPalPayment(WebhookEventType $eventType, string $payPalOrderId): ?PayPalPayment
    {
        $cartRecord = $this->cartRecordFinder->findByPayPalOrderId($payPalOrderId);

        if (!$cartRecord instanceof Cart) {
            // e.g. a payment of another shop using the same PayPal account
            $this->logger->info('Ignored a webhook of PayPal for an unknown order.', [
                'eventType' => $eventType->value,
                'payPalOrderId' => $payPalOrderId,
            ]);

            return null;
        }

        return PayPalPayment::fromCartRecord($cartRecord);
    }
}
