<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Functional;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Codappix\Typo3PhpDatasets\TestingFramework;
use Extcode\Cart\Domain\Model\Cart\Cart;
use Extcode\PaypalTest\EventListener\EventRecorder;
use Extcode\PaypalTest\Http\PayPalApiMock;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Provides an order paid with PayPal and its stored cart. The PayPal API is
 * answered by the PayPalApiMock of the test extension paypal_test.
 */
abstract class AbstractPayPalTestCase extends FunctionalTestCase
{
    use TestingFramework;

    protected const CART_HASH = 'cart-hash-0001';

    protected const CART_FAILURE_HASH = 'cart-failure-hash-0001';

    protected const PAYPAL_ORDER_ID = 'PAYPAL-ORDER-1';

    protected const CAPTURE_ID = 'CAPTURE-1';

    protected const TYPE_NUM = 2278101;

    protected array $testExtensionsToLoad = [
        'extcode/cart',
        'extcode/cart-paypal',
        'typo3conf/ext/cart_paypal/Tests/Functional/Fixtures/Extensions/paypal_test',
    ];

    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/cart_paypal/Tests/Functional/Fixtures/Sites/' => 'typo3conf/sites',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'cart_paypal' => [
                'credentials' => [
                    'clientId' => 'client-id',
                    'clientSecret' => 'client-secret',
                    'webhookId' => 'webhook-id',
                ],
            ],
        ],
        'FE' => [
            'cacheHash' => [
                'enforceValidation' => false,
            ],
        ],
        'MAIL' => [
            'transport' => 'null',
        ],
        'SYS' => [
            'features' => [
                // the MailHandler of cart is deprecated since cart 12.4
                'cart.useNewOrderMailSender' => true,
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importPHPDataSet(__DIR__ . '/Fixtures/DataSets/Base.php');
        $this->importPHPDataSet(__DIR__ . '/Fixtures/DataSets/Order.php');

        $cart = new Cart([]);
        $cart->setBillingCountry('de');
        $cart->setOrderNumber('ORD-2026-0001');

        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_cart_domain_model_cart')
            ->insert('tx_cart_domain_model_cart', [
                'uid' => 1,
                'pid' => 2,
                's_hash' => self::CART_HASH,
                'f_hash' => self::CART_FAILURE_HASH,
                'order_item' => 1,
                'serialized_cart' => serialize($cart),
            ]);

        EventRecorder::reset();
        PayPalApiMock::reset();
    }

    protected function tearDown(): void
    {
        self::assertSame([], PayPalApiMock::getUnusedResponses(), 'Not all prepared responses of PayPal were requested.');

        parent::tearDown();
    }

    /**
     * Calls an endpoint of the Api plugin on the page type of cart_paypal.
     *
     * @param string|null $hash null omits the hash argument
     */
    protected function callApi(
        string $action,
        string $method = 'GET',
        string $body = '',
        array $queryParameters = [],
        ?string $hash = self::CART_HASH,
        array $headers = [],
    ): ResponseInterface {
        $pluginArguments = [];
        if ($action !== '') {
            $pluginArguments['tx_cartpaypal_api[controller]'] = 'Api';
            $pluginArguments['tx_cartpaypal_api[action]'] = $action;
        }
        if ($hash !== null) {
            $pluginArguments['tx_cartpaypal_api[hash]'] = $hash;
        }

        $request = (new InternalRequest('http://localhost/'))
            ->withPageId(1)
            ->withQueryParameters(['type' => self::TYPE_NUM] + $pluginArguments + $queryParameters)
            ->withMethod($method);

        if ($body !== '') {
            $stream = new Stream('php://temp', 'rw');
            $stream->write($body);
            $stream->rewind();
            $request = $request->withBody($stream)->withHeader('Content-Type', 'application/json');
        }
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->executeFrontendSubRequest($request);
    }

    protected function decodeJson(ResponseInterface $response): array
    {
        $data = json_decode((string)$response->getBody(), true);
        self::assertIsArray($data, 'The response is no JSON: ' . $response->getBody());

        return $data;
    }

    /**
     * Stores the PayPal order the customer has approved as transaction.
     */
    protected function addTransaction(string $status = 'created'): void
    {
        $connectionPool = $this->get(ConnectionPool::class);
        $connectionPool
            ->getConnectionForTable('tx_cart_domain_model_order_transaction')
            ->insert('tx_cart_domain_model_order_transaction', [
                'uid' => 1,
                'pid' => 2,
                'payment' => 1,
                'txn_id' => self::PAYPAL_ORDER_ID,
                'status' => $status,
                'external_status_code' => 'PAYER_ACTION_REQUIRED',
            ]);
        $connectionPool
            ->getConnectionForTable('tx_cart_domain_model_order_payment')
            ->update('tx_cart_domain_model_order_payment', ['transactions' => 1], ['uid' => 1]);
    }

    protected function setPaymentStatus(string $status): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_cart_domain_model_order_payment')
            ->update('tx_cart_domain_model_order_payment', ['status' => $status], ['uid' => 1]);
    }

    protected function setPaymentProvider(string $provider): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_cart_domain_model_order_payment')
            ->update('tx_cart_domain_model_order_payment', ['provider' => $provider], ['uid' => 1]);
    }

    protected function setPaymentPid(int $paymentPid): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('sys_template');
        $constants = (string)$connection->select(['constants'], 'sys_template', ['uid' => 1])->fetchOne();
        $connection->update(
            'sys_template',
            ['constants' => str_replace('plugin.tx_cartpaypal.paymentPid = 3', 'plugin.tx_cartpaypal.paymentPid = ' . $paymentPid, $constants)],
            ['uid' => 1]
        );
    }

    protected function getPaymentStatus(): string
    {
        return (string)$this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_cart_domain_model_order_payment')
            ->select(['status'], 'tx_cart_domain_model_order_payment', ['uid' => 1])
            ->fetchOne();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function getTransactions(): array
    {
        return $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_cart_domain_model_order_transaction')
            ->select(
                ['payment', 'txn_id', 'status', 'external_status_code', 'note'],
                'tx_cart_domain_model_order_transaction',
                [],
                [],
                ['uid' => 'ASC']
            )
            ->fetchAllAssociative();
    }

    /**
     * The response of the Orders v2 API with the capture of the order.
     */
    protected static function capturedOrder(string $captureStatus = 'COMPLETED', string $value = '119.00'): array
    {
        return [
            'id' => self::PAYPAL_ORDER_ID,
            'status' => $captureStatus === 'COMPLETED' ? 'COMPLETED' : 'APPROVED',
            'purchase_units' => [
                [
                    'payments' => [
                        'captures' => [
                            self::capture($captureStatus, $value),
                        ],
                    ],
                ],
            ],
        ];
    }

    protected static function capture(string $status = 'COMPLETED', string $value = '119.00'): array
    {
        return [
            'id' => self::CAPTURE_ID,
            'status' => $status,
            'amount' => [
                'currency_code' => 'EUR',
                'value' => $value,
            ],
            'supplementary_data' => [
                'related_ids' => [
                    'order_id' => self::PAYPAL_ORDER_ID,
                ],
            ],
        ];
    }
}
