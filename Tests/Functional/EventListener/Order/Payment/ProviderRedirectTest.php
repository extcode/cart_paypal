<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Functional\EventListener\Order\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartPaypal\EventListener\Order\Payment\ProviderRedirect;
use Extcode\CartPaypal\Tests\Functional\AbstractPayPalTestCase;
use Extcode\PaypalTest\Http\PayPalApiMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The PaymentEvent is dispatched by the test extension paypal_test like by
 * the OrderController of cart after the order is stored.
 */
#[CoversClass(ProviderRedirect::class)]
final class ProviderRedirectTest extends AbstractPayPalTestCase
{
    #[Test]
    public function storesTheCartAndSendsTheCustomerToThePaymentPage(): void
    {
        $response = $this->dispatchPaymentEvent();

        self::assertSame(303, $response->getStatusCode());
        $cartRecord = $this->getNewCartRecord();
        self::assertSame(2, $cartRecord['pid']);
        self::assertNotSame('', $cartRecord['s_hash']);
        self::assertStringStartsWith('http://localhost/payment?', $response->getHeaderLine('Location'));
        self::assertStringContainsString(
            'tx_cartpaypal_payment%5Bhash%5D=' . $cartRecord['s_hash'],
            $response->getHeaderLine('Location')
        );
        self::assertSame([], PayPalApiMock::getRequests());
    }

    #[Test]
    public function sendsTheCustomerToPayPalWithoutPaymentPage(): void
    {
        $this->setPaymentPid(0);

        $response = $this->dispatchPaymentEvent();

        self::assertSame(303, $response->getStatusCode());
        $location = $response->getHeaderLine('Location');
        self::assertStringContainsString('type=2278101', $location);
        self::assertStringContainsString('tx_cartpaypal_api%5Baction%5D=redirect', $location);
        self::assertStringContainsString('tx_cartpaypal_api%5Bhash%5D=' . $this->getNewCartRecord()['s_hash'], $location);
    }

    #[Test]
    public function sendsCreditCardPaymentsToThePaymentPage(): void
    {
        $this->setPaymentProvider('PAYPAL_CREDITCARD');

        $response = $this->dispatchPaymentEvent();

        self::assertSame(303, $response->getStatusCode());
        self::assertStringStartsWith('http://localhost/payment?', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function ignoresOrdersOfOtherPaymentProviders(): void
    {
        $this->setPaymentProvider('GIROSOLUTION_PAYPAL');

        $response = $this->dispatchPaymentEvent();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('not stopped', (string)$response->getBody());
        self::assertSame(1, $this->countCartRecords());
    }

    private function dispatchPaymentEvent(): ResponseInterface
    {
        return $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))
                ->withPageId(1)
                ->withQueryParameters(['type' => 4711])
        );
    }

    /**
     * The cart stored in setUp() has the uid 1.
     */
    private function getNewCartRecord(): array
    {
        $cartRecord = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_cart_domain_model_cart')
            ->select(['pid', 's_hash', 'order_item'], 'tx_cart_domain_model_cart', ['uid' => 2])
            ->fetchAssociative();

        self::assertIsArray($cartRecord);
        self::assertSame(1, $cartRecord['order_item']);

        return $cartRecord;
    }

    private function countCartRecords(): int
    {
        return $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_cart_domain_model_cart')
            ->count('*', 'tx_cart_domain_model_cart', []);
    }
}
