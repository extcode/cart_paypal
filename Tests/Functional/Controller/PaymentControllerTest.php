<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Functional\Controller;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartPaypal\Controller\PaymentController;
use Extcode\CartPaypal\Tests\Functional\AbstractPayPalTestCase;
use Extcode\PaypalTest\Http\PayPalApiMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

#[CoversClass(PaymentController::class)]
final class PaymentControllerTest extends AbstractPayPalTestCase
{
    #[Test]
    public function showsThePayPalButtonWithTheEndpointsOfTheOrder(): void
    {
        $response = $this->showPaymentPage();

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getBody();
        self::assertStringContainsString('<paypal-button hidden>', $html);
        self::assertStringContainsString('data-client-id="client-id"', $html);
        self::assertStringContainsString('data-sdk-url="https://www.sandbox.paypal.com/web-sdk/v6/core"', $html);
        self::assertStringContainsString('data-currency-code="EUR"', $html);
        self::assertStringContainsString('data-amount="119.00"', $html);
        self::assertMatchesRegularExpression(
            '/data-create-order-url="[^"]*tx_cartpaypal_api%5Baction%5D=createOrder[^"]*tx_cartpaypal_api%5Bhash%5D=' . self::CART_HASH . '/',
            $html
        );
        self::assertMatchesRegularExpression('/data-capture-order-url="[^"]*captureOrder/', $html);
        self::assertMatchesRegularExpression(
            '/href="[^"]*tx_cartpaypal_api%5Baction%5D=cancel[^"]*tx_cartpaypal_api%5Bhash%5D=' . self::CART_FAILURE_HASH . '/',
            $html
        );
        self::assertStringContainsString('Payment.js', $html);
        self::assertStringNotContainsString('client-secret', $html);
        self::assertSame([], PayPalApiMock::getRequests());
    }

    #[Test]
    public function showsTheOrderWithAddressesAndTotal(): void
    {
        $html = (string)$this->showPaymentPage()->getBody();

        self::assertStringContainsString('Your order ORD-2026-0001 has been placed.', $html);
        self::assertMatchesRegularExpression('#Kanne\s*<br/><small class="text-muted">kanne</small>#', $html);
        self::assertStringContainsString('Shipping (Standard)', $html);
        self::assertStringContainsString('7,95', $html);
        self::assertStringContainsString('111,05', $html);
        self::assertStringContainsString('119,00', $html);
        self::assertStringContainsString('Erika Mustermann', $html);
        self::assertStringContainsString('Same as billing address', $html);
    }

    #[Test]
    public function creditCardPaymentShowsTheCardButton(): void
    {
        $this->setPaymentProvider('PAYPAL_CREDITCARD');

        $html = (string)$this->showPaymentPage()->getBody();

        self::assertStringContainsString('data-payment-method="card"', $html);
        self::assertStringContainsString('<paypal-basic-card-button hidden>', $html);
        self::assertStringNotContainsString('<paypal-button', $html);
        self::assertStringContainsString('a PayPal account is not required', $html);
    }

    #[Test]
    public function payPalPaymentShowsThePayPalButton(): void
    {
        $html = (string)$this->showPaymentPage()->getBody();

        self::assertStringContainsString('data-payment-method="paypal"', $html);
        self::assertStringNotContainsString('<paypal-basic-card-button', $html);
    }

    #[Test]
    public function paidOrderShowsTheOrder(): void
    {
        $this->setPaymentStatus('paid');

        $response = $this->showPaymentPage();

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('tx_cart_cart%5BorderItem%5D=1', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function canceledOrderLinksToTheCart(): void
    {
        $this->setPaymentStatus('canceled');

        $html = (string)$this->showPaymentPage()->getBody();

        self::assertStringContainsString('The payment of this order was canceled.', $html);
        self::assertStringNotContainsString('<paypal-button', $html);
    }

    #[Test]
    public function unknownCartShowsAMessage(): void
    {
        $html = (string)$this->showPaymentPage('unknown-cart-hash')->getBody();

        self::assertStringContainsString('The order could not be found.', $html);
        self::assertStringNotContainsString('<paypal-button', $html);
    }

    #[Test]
    public function orderOfAnotherPaymentProviderShowsAMessage(): void
    {
        $this->setPaymentProvider('GIROSOLUTION_PAYPAL');

        $html = (string)$this->showPaymentPage()->getBody();

        self::assertStringContainsString('The order could not be found.', $html);
    }

    private function showPaymentPage(string $hash = self::CART_HASH): ResponseInterface
    {
        return $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))
                ->withPageId(3)
                ->withQueryParameters(['tx_cartpaypal_payment[hash]' => $hash])
        );
    }
}
