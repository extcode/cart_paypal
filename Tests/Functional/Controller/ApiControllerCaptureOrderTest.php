<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Functional\Controller;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Order\History\OrderChangeRepository;
use Extcode\CartPaypal\Checkout\Payment\PaymentProcessor;
use Extcode\CartPaypal\Controller\ApiController;
use Extcode\CartPaypal\Event\Order\CancelEvent;
use Extcode\CartPaypal\Event\Order\FinishEvent;
use Extcode\CartPaypal\Tests\Functional\AbstractPayPalTestCase;
use Extcode\PaypalTest\EventListener\EventRecorder;
use Extcode\PaypalTest\Http\PayPalApiMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * The JavaScript captures the order after the customer approved it.
 */
#[CoversClass(ApiController::class)]
#[CoversClass(PaymentProcessor::class)]
final class ApiControllerCaptureOrderTest extends AbstractPayPalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->addTransaction();
    }

    #[Test]
    public function completedCaptureMarksTheOrderAsPaid(): void
    {
        $this->prepareCapture(201, self::capturedOrder());

        $data = $this->decodeJson($this->captureOrder());

        self::assertSame('paid', $data['status']);
        self::assertStringContainsString('tx_cart_cart%5Baction%5D=show', $data['redirectUrl']);
        self::assertStringContainsString('tx_cart_cart%5BorderItem%5D=1', $data['redirectUrl']);
        self::assertSame('paid', $this->getPaymentStatus());
        self::assertSame(
            [
                'payment' => 1,
                'txn_id' => self::PAYPAL_ORDER_ID,
                'status' => 'paid',
                'external_status_code' => 'COMPLETED',
                'note' => self::CAPTURE_ID,
            ],
            $this->getTransactions()[0]
        );
    }

    #[Test]
    public function completedCaptureDispatchesTheFinishEventOnce(): void
    {
        $this->prepareCapture(201, self::capturedOrder());

        $this->captureOrder();
        $this->captureOrder();

        self::assertCount(1, EventRecorder::getEvents()[FinishEvent::class] ?? []);
        self::assertArrayNotHasKey(CancelEvent::class, EventRecorder::getEvents());

        $event = EventRecorder::getEvents()[FinishEvent::class][0];
        self::assertInstanceOf(FinishEvent::class, $event);
        self::assertSame('ORD-2026-0001', $event->getOrderItem()->getOrderNumber());
        self::assertSame('1', (string)$event->getSettings()['settings']['cart']['pid']);
    }

    #[Test]
    public function completedCaptureIsStoredInTheOrderHistory(): void
    {
        $this->prepareCapture(201, self::capturedOrder());

        $this->captureOrder();

        $changes = $this->get(ConnectionPool::class)
            ->getConnectionForTable(OrderChangeRepository::TABLE_NAME)
            ->select(['old_value', 'new_value', 'source', 'initiator'], OrderChangeRepository::TABLE_NAME)
            ->fetchAllAssociative();

        self::assertSame(
            [
                [
                    'old_value' => 'open',
                    'new_value' => 'paid',
                    'source' => 'frontend',
                    'initiator' => 'cart_paypal',
                ],
            ],
            $changes
        );
    }

    #[Test]
    public function captureIsSentWithTheIdOfTheOrderAsRequestId(): void
    {
        $this->prepareCapture(201, self::capturedOrder());

        $this->captureOrder();

        $requests = PayPalApiMock::getRequests('/v2/checkout/orders/' . self::PAYPAL_ORDER_ID . '/capture');
        self::assertCount(1, $requests);
        self::assertSame('capture-' . self::PAYPAL_ORDER_ID, $requests[0]->getHeaderLine('PayPal-Request-Id'));
    }

    #[Test]
    public function pendingCaptureMarksThePaymentAsPendingWithoutFinishingTheOrder(): void
    {
        $this->prepareCapture(201, self::capturedOrder('PENDING'));

        $data = $this->decodeJson($this->captureOrder());

        self::assertSame('pending', $data['status']);
        self::assertArrayHasKey('redirectUrl', $data);
        self::assertSame('pending', $this->getPaymentStatus());
        self::assertSame('pending', $this->getTransactions()[0]['status']);
        self::assertSame([], EventRecorder::getEvents());
    }

    #[Test]
    public function declinedCaptureLetsTheCustomerTryAgain(): void
    {
        $this->prepareCapture(422, [
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [
                ['issue' => 'INSTRUMENT_DECLINED'],
            ],
        ]);

        $data = $this->decodeJson($this->captureOrder());

        self::assertSame('failed', $data['status']);
        self::assertArrayNotHasKey('redirectUrl', $data);
        self::assertNotSame('', $data['message']);
        self::assertSame('open', $this->getPaymentStatus());
        self::assertSame(
            ['status' => 'failed', 'external_status_code' => 'INSTRUMENT_DECLINED'],
            array_intersect_key($this->getTransactions()[0], ['status' => 0, 'external_status_code' => 0])
        );
        self::assertSame([], EventRecorder::getEvents());
    }

    #[Test]
    public function alreadyCapturedOrderIsReadFromPayPal(): void
    {
        $this->prepareCapture(422, [
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [
                ['issue' => 'ORDER_ALREADY_CAPTURED'],
            ],
        ]);
        PayPalApiMock::addResponse('GET', '/v2/checkout/orders/' . self::PAYPAL_ORDER_ID, 200, self::capturedOrder());

        $data = $this->decodeJson($this->captureOrder());

        self::assertSame('paid', $data['status']);
        self::assertSame('paid', $this->getPaymentStatus());
        self::assertCount(1, EventRecorder::getEvents()[FinishEvent::class] ?? []);
    }

    #[Test]
    public function captureWithAnotherAmountIsNotMarkedAsPaid(): void
    {
        $this->prepareCapture(201, self::capturedOrder('COMPLETED', '1.00'));

        $data = $this->decodeJson($this->captureOrder());

        self::assertSame('pending', $data['status']);
        self::assertSame('pending', $this->getPaymentStatus());
        self::assertSame([], EventRecorder::getEvents());
    }

    #[Test]
    public function paidOrderIsNotCapturedAgain(): void
    {
        $this->setPaymentStatus('paid');

        $data = $this->decodeJson($this->captureOrder());

        self::assertSame('paid', $data['status']);
        self::assertSame([], PayPalApiMock::getRequests());
        self::assertSame([], EventRecorder::getEvents());
    }

    #[Test]
    public function payPalOrderOfAnotherOrderIsRejected(): void
    {
        $response = $this->callApi('captureOrder', 'POST', '{"orderId": "PAYPAL-ORDER-OF-SOMEONE-ELSE"}');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame([], PayPalApiMock::getRequests());
        self::assertSame('open', $this->getPaymentStatus());
    }

    #[Test]
    public function captureWithoutOrderIdIsRejected(): void
    {
        $response = $this->callApi('captureOrder', 'POST', '{}');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame([], PayPalApiMock::getRequests());
    }

    #[Test]
    public function unknownCartIsRejected(): void
    {
        $response = $this->callApi('captureOrder', 'POST', '{"orderId": "' . self::PAYPAL_ORDER_ID . '"}', hash: 'unknown-cart-hash');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame([], PayPalApiMock::getRequests());
    }

    #[Test]
    public function unreachablePayPalIsAnsweredWithAnError(): void
    {
        $this->prepareCapture(503, ['name' => 'SERVICE_UNAVAILABLE']);

        $response = $this->captureOrder();

        self::assertSame(502, $response->getStatusCode());
        self::assertSame('open', $this->getPaymentStatus());
        self::assertSame('created', $this->getTransactions()[0]['status']);
    }

    private function prepareCapture(int $statusCode, array $body): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders/' . self::PAYPAL_ORDER_ID . '/capture', $statusCode, $body);
    }

    private function captureOrder(): ResponseInterface
    {
        return $this->callApi('captureOrder', 'POST', '{"orderId": "' . self::PAYPAL_ORDER_ID . '"}');
    }
}
