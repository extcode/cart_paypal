<?php

declare(strict_types=1);

namespace Extcode\PaypalTest\Http;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * Answers the requests to the PayPal API with prepared responses, registered
 * as handler of the HTTP client of TYPO3 in ext_localconf.php of paypal_test.
 * No request leaves the test.
 *
 * Requests and responses are stored statically, as frontend requests in
 * functional tests use their own instances of the services.
 */
final class PayPalApiMock
{
    /**
     * @var list<array{method: string, path: string, response: Response}>
     */
    private static array $responses = [];

    /**
     * @var list<RequestInterface>
     */
    private static array $requests = [];

    public static function reset(): void
    {
        self::$responses = [];
        self::$requests = [];
    }

    public static function addResponse(string $method, string $path, int $statusCode, array $body = []): void
    {
        self::$responses[] = [
            'method' => $method,
            'path' => $path,
            'response' => new Response(
                $statusCode,
                ['Content-Type' => 'application/json'],
                json_encode($body, JSON_THROW_ON_ERROR)
            ),
        ];
    }

    public static function addAccessToken(): void
    {
        self::addResponse('POST', '/v1/oauth2/token', 200, [
            'access_token' => 'access-token',
            'token_type' => 'Bearer',
            'expires_in' => 32400,
        ]);
    }

    /**
     * @return list<RequestInterface>
     */
    public static function getRequests(?string $path = null): array
    {
        if ($path === null) {
            return self::$requests;
        }

        return array_values(array_filter(
            self::$requests,
            static fn(RequestInterface $request): bool => $request->getUri()->getPath() === $path
        ));
    }

    /**
     * @return list<array{method: string, path: string}>
     */
    public static function getUnusedResponses(): array
    {
        return array_values(array_map(
            static fn(array $response): array => ['method' => $response['method'], 'path' => $response['path']],
            self::$responses
        ));
    }

    /**
     * The handler middleware, it answers every request itself.
     */
    public static function middleware(): callable
    {
        return static fn(callable $handler): callable => static function (RequestInterface $request, array $options): PromiseInterface {
            self::$requests[] = $request;

            foreach (self::$responses as $index => $response) {
                if ($response['method'] === $request->getMethod() && $response['path'] === $request->getUri()->getPath()) {
                    unset(self::$responses[$index]);
                    self::$responses = array_values(self::$responses);

                    return Create::promiseFor($response['response']);
                }
            }

            return Create::rejectionFor(new RuntimeException(
                'Unexpected request to the PayPal API: ' . $request->getMethod() . ' ' . $request->getUri(),
                1795600901
            ));
        };
    }
}
