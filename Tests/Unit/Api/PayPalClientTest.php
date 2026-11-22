<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Unit\Api;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartPaypal\Api\ApiException;
use Extcode\CartPaypal\Api\PayPalClient;
use Extcode\CartPaypal\Configuration\CredentialLoaderInterface;
use Extcode\CartPaypal\Configuration\CredentialLoaderRegistry;
use Extcode\PaypalTest\Http\PayPalApiMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\Client\GuzzleClientFactory;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(PayPalClient::class)]
#[CoversClass(ApiException::class)]
final class PayPalClientTest extends UnitTestCase
{
    private const CREDENTIALS = [
        'clientId' => 'client-id',
        'clientSecret' => 'client-secret',
        'webhookId' => 'webhook-id',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TYPO3_CONF_VARS']['HTTP'] = [
            'verify' => true,
            'handler' => [
                'paypal-api-mock' => PayPalApiMock::middleware(),
            ],
        ];
        PayPalApiMock::reset();
    }

    protected function tearDown(): void
    {
        self::assertSame([], PayPalApiMock::getUnusedResponses(), 'Not all prepared responses of PayPal were requested.');

        parent::tearDown();
    }

    public static function sandboxDataProvider(): \Generator
    {
        yield 'not configured' => [[], true];
        yield 'enabled' => [['sandbox' => '1'], true];
        yield 'disabled' => [['sandbox' => '0'], false];
        yield 'disabled as boolean' => [['sandbox' => false], false];
    }

    #[Test]
    #[DataProvider('sandboxDataProvider')]
    public function usesTheSandboxUnlessItIsDisabled(array $credentials, bool $expectedSandbox): void
    {
        $subject = $this->createSubject($credentials + self::CREDENTIALS);

        self::assertSame($expectedSandbox, $subject->isSandbox());
        self::assertSame(
            $expectedSandbox ? 'https://www.sandbox.paypal.com/web-sdk/v6/core' : 'https://www.paypal.com/web-sdk/v6/core',
            $subject->getSdkUrl()
        );
    }

    #[Test]
    public function sendsTheRequestsToTheLiveApiIfTheSandboxIsDisabled(): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('GET', '/v2/checkout/orders/ORDER-1', 200, ['id' => 'ORDER-1']);

        $this->createSubject(['sandbox' => '0'] + self::CREDENTIALS)->getOrder('ORDER-1');

        foreach (PayPalApiMock::getRequests() as $request) {
            self::assertSame('api-m.paypal.com', $request->getUri()->getHost());
        }
    }

    #[Test]
    public function returnsTheClientId(): void
    {
        self::assertSame('client-id', $this->createSubject(self::CREDENTIALS)->getClientId());
    }

    #[Test]
    public function requestsTheAccessTokenOnlyOnce(): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('GET', '/v2/checkout/orders/ORDER-1', 200, ['id' => 'ORDER-1']);
        PayPalApiMock::addResponse('GET', '/v2/checkout/orders/ORDER-2', 200, ['id' => 'ORDER-2']);

        $subject = $this->createSubject(self::CREDENTIALS);

        self::assertSame(['id' => 'ORDER-1'], $subject->getOrder('ORDER-1'));
        self::assertSame(['id' => 'ORDER-2'], $subject->getOrder('ORDER-2'));
        self::assertCount(1, PayPalApiMock::getRequests('/v1/oauth2/token'));
    }

    #[Test]
    public function createsOrdersWithANewRequestIdEachTime(): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders', 201, ['id' => 'ORDER-1']);
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders', 201, ['id' => 'ORDER-2']);

        $subject = $this->createSubject(self::CREDENTIALS);
        $subject->createOrder(['intent' => 'CAPTURE']);
        $subject->createOrder(['intent' => 'CAPTURE']);

        $requests = PayPalApiMock::getRequests('/v2/checkout/orders');
        self::assertSame('{"intent":"CAPTURE"}', (string)$requests[0]->getBody());
        self::assertSame('application/json', $requests[0]->getHeaderLine('Content-Type'));
        self::assertSame('return=representation', $requests[0]->getHeaderLine('Prefer'));
        self::assertNotSame($requests[0]->getHeaderLine('PayPal-Request-Id'), $requests[1]->getHeaderLine('PayPal-Request-Id'));
    }

    #[Test]
    public function capturesWithAnEmptyObjectAsBody(): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders/ORDER-1/capture', 201, ['id' => 'ORDER-1', 'status' => 'COMPLETED']);

        $this->createSubject(self::CREDENTIALS)->captureOrder('ORDER-1');

        $request = PayPalApiMock::getRequests('/v2/checkout/orders/ORDER-1/capture')[0];
        self::assertSame('{}', (string)$request->getBody());
        self::assertSame('capture-ORDER-1', $request->getHeaderLine('PayPal-Request-Id'));
    }

    #[Test]
    public function errorResponsesThrowAnExceptionWithTheIssue(): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders/ORDER-1/capture', 422, [
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [
                ['issue' => 'ORDER_ALREADY_CAPTURED'],
            ],
            'debug_id' => 'debug-1',
        ]);

        try {
            $this->createSubject(self::CREDENTIALS)->captureOrder('ORDER-1');
            self::fail('No exception has been thrown.');
        } catch (ApiException $exception) {
            self::assertSame(422, $exception->getStatusCode());
            self::assertSame('UNPROCESSABLE_ENTITY', $exception->getName());
            self::assertSame('ORDER_ALREADY_CAPTURED', $exception->getIssue());
            self::assertSame('debug-1', $exception->getDebugId());
            self::assertSame(
                'The PayPal API answered with status 422: UNPROCESSABLE_ENTITY ORDER_ALREADY_CAPTURED',
                $exception->getMessage()
            );
        }
    }

    #[Test]
    public function rejectedCredentialsThrowAnException(): void
    {
        PayPalApiMock::addResponse('POST', '/v1/oauth2/token', 401, [
            'error' => 'invalid_client',
            'error_description' => 'Client Authentication failed',
        ]);

        $this->expectExceptionObject(
            new ApiException('The PayPal API answered with status 401: invalid_client', 1795600001)
        );

        $this->createSubject(self::CREDENTIALS)->getOrder('ORDER-1');
    }

    #[Test]
    public function missingCredentialsThrowAnExceptionWithoutRequest(): void
    {
        $this->expectExceptionObject(
            new ApiException('The credentials of PayPal (clientId, clientSecret) are not configured.', 1795600002)
        );

        $this->createSubject(['clientId' => 'client-id'])->getOrder('ORDER-1');
    }

    #[Test]
    public function webhookWithoutConfiguredWebhookIdIsNotVerified(): void
    {
        $subject = $this->createSubject(['webhookId' => ''] + self::CREDENTIALS);

        self::assertFalse($subject->verifyWebhookSignature($this->createWebhookRequest(), '{}'));
        self::assertSame([], PayPalApiMock::getRequests());
    }

    #[Test]
    public function webhookWithMissingHeaderIsNotVerified(): void
    {
        $request = $this->createWebhookRequest()->withoutHeader('PayPal-Transmission-Sig');

        self::assertFalse($this->createSubject(self::CREDENTIALS)->verifyWebhookSignature($request, '{}'));
        self::assertSame([], PayPalApiMock::getRequests());
    }

    #[Test]
    public function webhookWithInvalidJsonIsNotVerified(): void
    {
        self::assertFalse(
            $this->createSubject(self::CREDENTIALS)->verifyWebhookSignature($this->createWebhookRequest(), '{"id":')
        );
        self::assertSame([], PayPalApiMock::getRequests());
    }

    public static function verificationStatusDataProvider(): \Generator
    {
        yield 'success' => ['SUCCESS', true];
        yield 'failure' => ['FAILURE', false];
    }

    #[Test]
    #[DataProvider('verificationStatusDataProvider')]
    public function webhookIsVerifiedByPayPal(string $verificationStatus, bool $expected): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v1/notifications/verify-webhook-signature', 200, [
            'verification_status' => $verificationStatus,
        ]);
        $body = '{"id":"WH-1","resource":{"amount":{"value":"1.10"},"href":"https:\/\/example.org\/"}}';

        self::assertSame(
            $expected,
            $this->createSubject(self::CREDENTIALS)->verifyWebhookSignature($this->createWebhookRequest(), $body)
        );

        $verification = (string)PayPalApiMock::getRequests('/v1/notifications/verify-webhook-signature')[0]->getBody();
        self::assertSame(
            '{"auth_algo":"SHA256withRSA","cert_url":"https://api-m.paypal.com/v1/notifications/certs/CERT-1",'
            . '"transmission_id":"transmission-1","transmission_sig":"signature",'
            . '"transmission_time":"2026-11-20T10:00:00Z","webhook_id":"webhook-id","webhook_event":' . $body . '}',
            $verification
        );
    }

    private function createSubject(array $credentials): PayPalClient
    {
        $loader = self::createStub(CredentialLoaderInterface::class);
        $loader->method('getCredentials')->willReturn($credentials);

        return new PayPalClient(
            new RequestFactory(new GuzzleClientFactory()),
            new CredentialLoaderRegistry([$loader])
        );
    }

    private function createWebhookRequest(): ServerRequest
    {
        return (new ServerRequest('https://example.org/', 'POST'))
            ->withHeader('PayPal-Auth-Algo', 'SHA256withRSA')
            ->withHeader('PayPal-Cert-Url', 'https://api-m.paypal.com/v1/notifications/certs/CERT-1')
            ->withHeader('PayPal-Transmission-Id', 'transmission-1')
            ->withHeader('PayPal-Transmission-Sig', 'signature')
            ->withHeader('PayPal-Transmission-Time', '2026-11-20T10:00:00Z');
    }
}
