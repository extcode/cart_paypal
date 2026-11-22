<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Functional\Controller;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartPaypal\Controller\ApiController;
use Extcode\CartPaypal\Event\Order\CancelEvent;
use Extcode\CartPaypal\Event\Order\FinishEvent;
use Extcode\CartPaypal\Tests\Functional\AbstractPayPalTestCase;
use Extcode\PaypalTest\EventListener\EventRecorder;
use Extcode\PaypalTest\Http\PayPalApiMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * The customer is sent to PayPal and returns to the shop, without the
 * JavaScript of the payment page.
 */
#[CoversClass(ApiController::class)]
final class ApiControllerRedirectFlowTest extends AbstractPayPalTestCase
{
    private const APPROVE_URL = 'https://www.sandbox.paypal.com/checkoutnow?token=' . self::PAYPAL_ORDER_ID;

    #[Test]
    public function redirectSendsTheCustomerToPayPal(): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders', 200, [
            'id' => self::PAYPAL_ORDER_ID,
            'status' => 'PAYER_ACTION_REQUIRED',
            'links' => [
                ['href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/' . self::PAYPAL_ORDER_ID, 'rel' => 'self'],
                ['href' => self::APPROVE_URL, 'rel' => 'payer-action'],
            ],
        ]);

        $response = $this->callApi('redirect');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(self::APPROVE_URL, $response->getHeaderLine('Location'));
        self::assertSame('created', $this->getTransactions()[0]['status']);
    }

    #[Test]
    public function redirectOfACreditCardPaymentSendsTheCustomerToPayPalWithReturnUrls(): void
    {
        $this->setPaymentProvider('PAYPAL_CREDITCARD');
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders', 200, [
            'id' => self::PAYPAL_ORDER_ID,
            'status' => 'PAYER_ACTION_REQUIRED',
            'links' => [
                ['href' => self::APPROVE_URL, 'rel' => 'payer-action'],
            ],
        ]);

        $response = $this->callApi('redirect');

        self::assertSame(self::APPROVE_URL, $response->getHeaderLine('Location'));
        $order = json_decode((string)PayPalApiMock::getRequests('/v2/checkout/orders')[0]->getBody(), true);
        self::assertArrayHasKey('return_url', $order['payment_source']['paypal']['experience_context']);
    }

    #[Test]
    public function redirectOfAPaidOrderShowsTheOrder(): void
    {
        $this->setPaymentStatus('paid');

        $response = $this->callApi('redirect');

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('tx_cart_cart%5BorderItem%5D=1', $response->getHeaderLine('Location'));
        self::assertSame([], PayPalApiMock::getRequests());
    }

    #[Test]
    public function returnCapturesTheOrderAndShowsIt(): void
    {
        $this->addTransaction();
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders/' . self::PAYPAL_ORDER_ID . '/capture', 201, self::capturedOrder());

        $response = $this->callApi('return', queryParameters: ['token' => self::PAYPAL_ORDER_ID, 'PayerID' => 'PAYER-1']);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('tx_cart_cart%5BorderItem%5D=1', $response->getHeaderLine('Location'));
        self::assertSame('paid', $this->getPaymentStatus());
        self::assertCount(1, EventRecorder::getEvents()[FinishEvent::class] ?? []);
    }

    #[Test]
    public function returnWithDeclinedPaymentSendsTheCustomerBackToThePaymentPage(): void
    {
        $this->addTransaction();
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders/' . self::PAYPAL_ORDER_ID . '/capture', 422, [
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [['issue' => 'INSTRUMENT_DECLINED']],
        ]);

        $response = $this->callApi('return', queryParameters: ['token' => self::PAYPAL_ORDER_ID]);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('/payment', $response->getHeaderLine('Location'));
        self::assertStringContainsString('tx_cartpaypal_payment%5Bhash%5D=' . self::CART_HASH, $response->getHeaderLine('Location'));
        self::assertSame('open', $this->getPaymentStatus());
        self::assertSame([], EventRecorder::getEvents());
    }

    #[Test]
    public function returnWithDeclinedPaymentWithoutPaymentPageCancelsThePayment(): void
    {
        $this->setPaymentPid(0);
        $this->addTransaction();
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders/' . self::PAYPAL_ORDER_ID . '/capture', 422, [
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [['issue' => 'INSTRUMENT_DECLINED']],
        ]);

        $response = $this->callApi('return', queryParameters: ['token' => self::PAYPAL_ORDER_ID]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('http://localhost/', $response->getHeaderLine('Location'));
        self::assertSame('canceled', $this->getPaymentStatus());
        self::assertCount(1, EventRecorder::getEvents()[CancelEvent::class] ?? []);
    }

    #[Test]
    public function returnWithAnotherPayPalOrderDoesNotCapture(): void
    {
        $this->addTransaction();

        $response = $this->callApi('return', queryParameters: ['token' => 'PAYPAL-ORDER-OF-SOMEONE-ELSE']);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('http://localhost/', $response->getHeaderLine('Location'));
        self::assertSame([], PayPalApiMock::getRequests());
        self::assertSame('open', $this->getPaymentStatus());
    }

    #[Test]
    public function cancelCancelsThePaymentAndReturnsToTheCart(): void
    {
        $this->addTransaction();

        $response = $this->callApi('cancel', hash: self::CART_FAILURE_HASH);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('http://localhost/', $response->getHeaderLine('Location'));
        self::assertSame('canceled', $this->getPaymentStatus());
        self::assertSame('canceled', $this->getTransactions()[0]['status']);
        self::assertCount(1, EventRecorder::getEvents()[CancelEvent::class] ?? []);
        self::assertArrayNotHasKey(FinishEvent::class, EventRecorder::getEvents());
    }

    #[Test]
    public function repeatedCancelDispatchesTheCancelEventOnce(): void
    {
        $this->callApi('cancel', hash: self::CART_FAILURE_HASH);
        $this->callApi('cancel', hash: self::CART_FAILURE_HASH);

        self::assertCount(1, EventRecorder::getEvents()[CancelEvent::class] ?? []);
    }

    #[Test]
    public function cancelOfAPaidOrderShowsTheOrder(): void
    {
        $this->setPaymentStatus('paid');

        $response = $this->callApi('cancel', hash: self::CART_FAILURE_HASH);

        self::assertStringContainsString('tx_cart_cart%5BorderItem%5D=1', $response->getHeaderLine('Location'));
        self::assertSame('paid', $this->getPaymentStatus());
        self::assertSame([], EventRecorder::getEvents());
    }

    #[Test]
    public function cancelWithTheSuccessHashIsRejected(): void
    {
        $response = $this->callApi('cancel', hash: self::CART_HASH);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('http://localhost/', $response->getHeaderLine('Location'));
        self::assertSame('open', $this->getPaymentStatus());
        self::assertSame([], EventRecorder::getEvents());
    }

    #[Test]
    public function unknownCartReturnsToTheCart(): void
    {
        $response = $this->callApi('cancel', hash: 'unknown-cart-hash');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('http://localhost/', $response->getHeaderLine('Location'));
        self::assertSame('open', $this->getPaymentStatus());
    }
}
