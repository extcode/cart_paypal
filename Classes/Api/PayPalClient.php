<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Api;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartPaypal\Configuration\CredentialLoaderRegistry;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Calls the PayPal REST API (Orders v2, webhook verification).
 *
 * Uses the HTTP client of TYPO3 instead of an SDK, so the extension has no
 * further dependencies.
 */
final class PayPalClient
{
    private const API_LIVE = 'https://api-m.paypal.com';

    private const API_SANDBOX = 'https://api-m.sandbox.paypal.com';

    private const SDK_LIVE = 'https://www.paypal.com/web-sdk/v6/core';

    private const SDK_SANDBOX = 'https://www.sandbox.paypal.com/web-sdk/v6/core';

    private const TIMEOUT = 30;

    private ?array $credentials = null;

    private ?string $accessToken = null;

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly CredentialLoaderRegistry $credentialLoaderRegistry,
    ) {}

    /**
     * The client id is public, the JavaScript SDK is initialized with it.
     */
    public function getClientId(): string
    {
        return $this->getCredential('clientId');
    }

    /**
     * Uses the sandbox unless it is disabled explicitly.
     */
    public function isSandbox(): bool
    {
        $credentials = $this->getCredentials();

        if (!array_key_exists('sandbox', $credentials)) {
            return true;
        }

        return filter_var($credentials['sandbox'], FILTER_VALIDATE_BOOL);
    }

    public function getSdkUrl(): string
    {
        return $this->isSandbox() ? self::SDK_SANDBOX : self::SDK_LIVE;
    }

    /**
     * @throws ApiException
     */
    public function createOrder(array $order): array
    {
        return $this->send(
            'POST',
            '/v2/checkout/orders',
            $this->encode($order),
            [
                'PayPal-Request-Id' => 'create-' . bin2hex(random_bytes(16)),
                'Prefer' => 'return=representation',
            ]
        );
    }

    /**
     * Captures the approved order. The request id depends on the order only,
     * so PayPal treats a repeated capture of the same order as one request.
     *
     * @throws ApiException
     */
    public function captureOrder(string $orderId): array
    {
        return $this->send(
            'POST',
            '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture',
            $this->encode(new stdClass()),
            [
                'PayPal-Request-Id' => 'capture-' . $orderId,
                'Prefer' => 'return=representation',
            ]
        );
    }

    /**
     * @throws ApiException
     */
    public function getOrder(string $orderId): array
    {
        return $this->send('GET', '/v2/checkout/orders/' . rawurlencode($orderId));
    }

    /**
     * Lets PayPal verify the signature of a received webhook.
     *
     * The received body is passed on unchanged, decoding and encoding it again
     * could change it and let the verification fail.
     *
     * @throws ApiException
     */
    public function verifyWebhookSignature(ServerRequestInterface $request, string $body): bool
    {
        $webhookId = $this->getCredential('webhookId');
        if ($webhookId === '') {
            return false;
        }

        $verification = [
            'auth_algo' => $request->getHeaderLine('PayPal-Auth-Algo'),
            'cert_url' => $request->getHeaderLine('PayPal-Cert-Url'),
            'transmission_id' => $request->getHeaderLine('PayPal-Transmission-Id'),
            'transmission_sig' => $request->getHeaderLine('PayPal-Transmission-Sig'),
            'transmission_time' => $request->getHeaderLine('PayPal-Transmission-Time'),
            'webhook_id' => $webhookId,
        ];
        if (in_array('', $verification, true)) {
            return false;
        }

        try {
            json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        $payload = substr($this->encode($verification), 0, -1) . ',"webhook_event":' . $body . '}';

        $response = $this->send('POST', '/v1/notifications/verify-webhook-signature', $payload);

        return ($response['verification_status'] ?? '') === 'SUCCESS';
    }

    /**
     * @throws ApiException
     */
    private function send(string $method, string $path, ?string $body = null, array $headers = []): array
    {
        $headers['Authorization'] = 'Bearer ' . $this->getAccessToken();
        $headers['Accept'] = 'application/json';

        $options = [
            'headers' => $headers,
            'http_errors' => false,
            'timeout' => self::TIMEOUT,
        ];
        if ($body !== null) {
            $options['headers']['Content-Type'] = 'application/json';
            $options['body'] = $body;
        }

        return $this->request($method, $path, $options);
    }

    /**
     * @throws ApiException
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $clientId = $this->getCredential('clientId');
        $clientSecret = $this->getCredential('clientSecret');
        if ($clientId === '' || $clientSecret === '') {
            throw new ApiException('The credentials of PayPal (clientId, clientSecret) are not configured.', 1795600002);
        }

        $response = $this->request('POST', '/v1/oauth2/token', [
            'auth' => [$clientId, $clientSecret],
            'form_params' => [
                'grant_type' => 'client_credentials',
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
            'timeout' => self::TIMEOUT,
        ]);

        $accessToken = (string)($response['access_token'] ?? '');
        if ($accessToken === '') {
            throw new ApiException('PayPal did not return an access token.', 1795600003);
        }

        return $this->accessToken = $accessToken;
    }

    /**
     * @throws ApiException
     */
    private function request(string $method, string $path, array $options): array
    {
        try {
            $response = $this->requestFactory->request($this->getApiUrl() . $path, $method, $options);
        } catch (GuzzleException $exception) {
            throw new ApiException(
                'PayPal could not be reached: ' . $exception->getMessage(),
                1795600004,
                previous: $exception
            );
        }

        $data = json_decode((string)$response->getBody(), true);
        if (!is_array($data)) {
            $data = [];
        }

        if ($response->getStatusCode() >= 400) {
            throw ApiException::fromResponse($response->getStatusCode(), $data);
        }

        return $data;
    }

    private function encode(array|stdClass $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function getApiUrl(): string
    {
        return $this->isSandbox() ? self::API_SANDBOX : self::API_LIVE;
    }

    private function getCredential(string $name): string
    {
        $value = $this->getCredentials()[$name] ?? '';

        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function getCredentials(): array
    {
        return $this->credentials ??= $this->credentialLoaderRegistry->getCredentials();
    }
}
