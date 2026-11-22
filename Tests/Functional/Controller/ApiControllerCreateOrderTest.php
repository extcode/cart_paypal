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
use Extcode\CartPaypal\Tests\Functional\AbstractPayPalTestCase;
use Extcode\PaypalTest\Http\PayPalApiMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ApiController::class)]
final class ApiControllerCreateOrderTest extends AbstractPayPalTestCase
{
    #[Test]
    public function createsTheOrderAtPayPalAndStoresItAsTransaction(): void
    {
        $this->preparePayPalOrder();

        $response = $this->callApi('createOrder', 'POST');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['orderId' => self::PAYPAL_ORDER_ID], $this->decodeJson($response));
        self::assertSame(
            [
                [
                    'payment' => 1,
                    'txn_id' => self::PAYPAL_ORDER_ID,
                    'status' => 'created',
                    'external_status_code' => 'PAYER_ACTION_REQUIRED',
                    'note' => '',
                ],
            ],
            $this->getTransactions()
        );
        self::assertSame('open', $this->getPaymentStatus());
    }

    #[Test]
    public function sendsTheAmountOfTheStoredOrder(): void
    {
        $this->preparePayPalOrder();

        $this->callApi('createOrder', 'POST', '{"amount": "0.01"}');

        $requests = PayPalApiMock::getRequests('/v2/checkout/orders');
        self::assertCount(1, $requests);
        self::assertSame('Bearer access-token', $requests[0]->getHeaderLine('Authorization'));
        self::assertStringStartsWith('create-', $requests[0]->getHeaderLine('PayPal-Request-Id'));

        $order = json_decode((string)$requests[0]->getBody(), true);
        self::assertSame('CAPTURE', $order['intent']);
        self::assertSame(
            [
                'custom_id' => '1',
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => '119.00',
                ],
                'invoice_id' => 'ORD-2026-0001',
            ],
            $order['purchase_units'][0]
        );

        $experienceContext = $order['payment_source']['paypal']['experience_context'];
        self::assertSame('PAY_NOW', $experienceContext['user_action']);
        self::assertSame('NO_SHIPPING', $experienceContext['shipping_preference']);
        self::assertStringContainsString('tx_cartpaypal_api%5Baction%5D=return', $experienceContext['return_url']);
        self::assertStringContainsString('tx_cartpaypal_api%5Bhash%5D=' . self::CART_HASH, $experienceContext['return_url']);
        self::assertStringContainsString('tx_cartpaypal_api%5Baction%5D=cancel', $experienceContext['cancel_url']);
        self::assertStringContainsString('tx_cartpaypal_api%5Bhash%5D=' . self::CART_FAILURE_HASH, $experienceContext['cancel_url']);
    }

    #[Test]
    public function createsTheOrderForTheCardButtonWithoutPaymentSource(): void
    {
        $this->setPaymentProvider('PAYPAL_CREDITCARD');
        $this->preparePayPalOrder();

        $response = $this->callApi('createOrder', 'POST');

        self::assertSame(['orderId' => self::PAYPAL_ORDER_ID], $this->decodeJson($response));
        $order = json_decode((string)PayPalApiMock::getRequests('/v2/checkout/orders')[0]->getBody(), true);
        self::assertSame(['intent', 'purchase_units'], array_keys($order));
        self::assertSame('119.00', $order['purchase_units'][0]['amount']['value']);
        self::assertSame(self::PAYPAL_ORDER_ID, $this->getTransactions()[0]['txn_id']);
    }

    #[Test]
    public function requestsTheAccessTokenWithTheCredentials(): void
    {
        $this->preparePayPalOrder();

        $this->callApi('createOrder', 'POST');

        $requests = PayPalApiMock::getRequests('/v1/oauth2/token');
        self::assertCount(1, $requests);
        self::assertSame('api-m.sandbox.paypal.com', $requests[0]->getUri()->getHost());
        self::assertSame('Basic ' . base64_encode('client-id:client-secret'), $requests[0]->getHeaderLine('Authorization'));
        self::assertSame('grant_type=client_credentials', (string)$requests[0]->getBody());
    }

    #[Test]
    public function rejectsOtherMethodsThanPost(): void
    {
        $response = $this->callApi('createOrder');

        self::assertSame(405, $response->getStatusCode());
        self::assertSame([], PayPalApiMock::getRequests());
    }

    #[Test]
    public function rejectsUnknownCarts(): void
    {
        $response = $this->callApi('createOrder', 'POST', hash: 'unknown-cart-hash');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame([], PayPalApiMock::getRequests());
    }

    #[Test]
    public function rejectsPaidOrders(): void
    {
        $this->setPaymentStatus('paid');

        $response = $this->callApi('createOrder', 'POST');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame([], PayPalApiMock::getRequests());
    }

    #[Test]
    public function rejectsOrdersOfOtherPaymentProviders(): void
    {
        $this->setPaymentProvider('GIROSOLUTION_PAYPAL');

        $response = $this->callApi('createOrder', 'POST');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame([], PayPalApiMock::getRequests());
    }

    #[Test]
    public function answersWithAnErrorIfPayPalFails(): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders', 500, [
            'name' => 'INTERNAL_SERVER_ERROR',
            'debug_id' => 'debug-1',
        ]);

        $response = $this->callApi('createOrder', 'POST');

        self::assertSame(502, $response->getStatusCode());
        self::assertArrayHasKey('message', $this->decodeJson($response));
        self::assertSame([], $this->getTransactions());
    }

    private function preparePayPalOrder(): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders', 200, [
            'id' => self::PAYPAL_ORDER_ID,
            'status' => 'PAYER_ACTION_REQUIRED',
            'links' => [
                [
                    'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=' . self::PAYPAL_ORDER_ID,
                    'rel' => 'payer-action',
                    'method' => 'GET',
                ],
            ],
        ]);
    }
}
