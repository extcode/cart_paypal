<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Functional\Controller;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartPaypal\Checkout\Webhook\WebhookHandler;
use Extcode\CartPaypal\Controller\WebhookController;
use Extcode\CartPaypal\Event\Order\FinishEvent;
use Extcode\CartPaypal\Tests\Functional\AbstractPayPalTestCase;
use Extcode\PaypalTest\EventListener\EventRecorder;
use Extcode\PaypalTest\Http\PayPalApiMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * PayPal sends webhooks to the page type without plugin arguments.
 */
#[CoversClass(WebhookController::class)]
#[CoversClass(WebhookHandler::class)]
final class WebhookControllerTest extends AbstractPayPalTestCase
{
    private const SIGNATURE_HEADERS = [
        'PayPal-Auth-Algo' => 'SHA256withRSA',
        'PayPal-Cert-Url' => 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-1',
        'PayPal-Transmission-Id' => 'transmission-1',
        'PayPal-Transmission-Sig' => 'signature',
        'PayPal-Transmission-Time' => '2026-11-20T10:00:00Z',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->addTransaction();
    }

    #[Test]
    public function completedCaptureOfACreditCardPaymentMarksTheOrderAsPaid(): void
    {
        $this->setPaymentProvider('PAYPAL_CREDITCARD');
        $this->prepareVerification('SUCCESS');

        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED', self::capture());

        self::assertSame('paid', $this->getPaymentStatus());
        self::assertCount(1, EventRecorder::getEvents()[FinishEvent::class] ?? []);
    }

    #[Test]
    public function completedCaptureMarksTheOrderAsPaid(): void
    {
        $this->prepareVerification('SUCCESS');

        $response = $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED', self::capture());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('paid', $this->getPaymentStatus());
        self::assertSame(['status' => 'paid', 'note' => self::CAPTURE_ID], array_intersect_key(
            $this->getTransactions()[0],
            ['status' => 0, 'note' => 0]
        ));
        self::assertCount(1, EventRecorder::getEvents()[FinishEvent::class] ?? []);
    }

    #[Test]
    public function repeatedWebhookFinishesTheOrderOnce(): void
    {
        $this->prepareVerification('SUCCESS');
        $this->prepareVerification('SUCCESS');

        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED', self::capture());
        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED', self::capture());

        self::assertCount(1, EventRecorder::getEvents()[FinishEvent::class] ?? []);
    }

    #[Test]
    public function verificationGetsTheReceivedEventUnchanged(): void
    {
        $this->prepareVerification('SUCCESS');

        $body = $this->buildEvent('PAYMENT.CAPTURE.COMPLETED', self::capture());
        $this->callApi('', 'POST', $body, hash: null, headers: self::SIGNATURE_HEADERS);

        $requests = PayPalApiMock::getRequests('/v1/notifications/verify-webhook-signature');
        self::assertCount(1, $requests);
        $verification = (string)$requests[0]->getBody();
        self::assertStringEndsWith(',"webhook_event":' . $body . '}', $verification);
        self::assertSame(
            [
                'auth_algo' => 'SHA256withRSA',
                'cert_url' => 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-1',
                'transmission_id' => 'transmission-1',
                'transmission_sig' => 'signature',
                'transmission_time' => '2026-11-20T10:00:00Z',
                'webhook_id' => 'webhook-id',
            ],
            array_diff_key(json_decode($verification, true), ['webhook_event' => 0])
        );
    }

    #[Test]
    public function approvedOrderIsCaptured(): void
    {
        $this->prepareVerification('SUCCESS');
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders/' . self::PAYPAL_ORDER_ID . '/capture', 201, self::capturedOrder());

        $response = $this->sendWebhook('CHECKOUT.ORDER.APPROVED', [
            'id' => self::PAYPAL_ORDER_ID,
            'status' => 'APPROVED',
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('paid', $this->getPaymentStatus());
        self::assertCount(1, EventRecorder::getEvents()[FinishEvent::class] ?? []);
    }

    #[Test]
    public function pendingCaptureMarksThePaymentAsPending(): void
    {
        $this->prepareVerification('SUCCESS');

        $this->sendWebhook('PAYMENT.CAPTURE.PENDING', self::capture('PENDING'));

        self::assertSame('pending', $this->getPaymentStatus());
        self::assertSame([], EventRecorder::getEvents());
    }

    #[Test]
    public function deniedCaptureCancelsAPendingPayment(): void
    {
        $this->setPaymentStatus('pending');
        $this->prepareVerification('SUCCESS');

        $this->sendWebhook('PAYMENT.CAPTURE.DENIED', self::capture('DECLINED'));

        self::assertSame('canceled', $this->getPaymentStatus());
        self::assertSame('failed', $this->getTransactions()[0]['status']);
    }

    #[Test]
    public function lateWebhookDoesNotChangeAPaidOrder(): void
    {
        $this->setPaymentStatus('paid');
        $this->prepareVerification('SUCCESS');

        $this->sendWebhook('PAYMENT.CAPTURE.PENDING', self::capture('PENDING'));

        self::assertSame('paid', $this->getPaymentStatus());
    }

    #[Test]
    public function webhookOfAnUnknownOrderIsAcknowledged(): void
    {
        $this->prepareVerification('SUCCESS');
        $capture = self::capture();
        $capture['supplementary_data']['related_ids']['order_id'] = 'PAYPAL-ORDER-OF-ANOTHER-SHOP';

        $response = $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED', $capture);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('open', $this->getPaymentStatus());
    }

    #[Test]
    public function webhookWithInvalidSignatureIsRejected(): void
    {
        $this->prepareVerification('FAILURE');

        $response = $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED', self::capture());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('open', $this->getPaymentStatus());
        self::assertSame([], EventRecorder::getEvents());
    }

    #[Test]
    public function webhookWithoutSignatureIsRejectedWithoutAskingPayPal(): void
    {
        $response = $this->callApi('', 'POST', $this->buildEvent('PAYMENT.CAPTURE.COMPLETED', self::capture()), hash: null);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame([], PayPalApiMock::getRequests());
        self::assertSame('open', $this->getPaymentStatus());
    }

    #[Test]
    public function otherMethodsThanPostAreRejected(): void
    {
        $response = $this->callApi('', hash: null);

        self::assertSame(405, $response->getStatusCode());
    }

    private function prepareVerification(string $verificationStatus): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v1/notifications/verify-webhook-signature', 200, [
            'verification_status' => $verificationStatus,
        ]);
    }

    private function sendWebhook(string $eventType, array $resource): ResponseInterface
    {
        return $this->callApi('', 'POST', $this->buildEvent($eventType, $resource), hash: null, headers: self::SIGNATURE_HEADERS);
    }

    private function buildEvent(string $eventType, array $resource): string
    {
        return json_encode([
            'id' => 'WH-1',
            'event_version' => '1.0',
            'resource_type' => str_starts_with($eventType, 'PAYMENT') ? 'capture' : 'checkout-order',
            'event_type' => $eventType,
            'resource' => $resource,
            'links' => [['href' => 'https://api-m.sandbox.paypal.com/v1/notifications/webhooks-events/WH-1', 'rel' => 'self']],
        ], JSON_THROW_ON_ERROR);
    }
}
