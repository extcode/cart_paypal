<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Unit\EventListener\Order\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart\Cart;
use Extcode\Cart\Domain\Model\Cart\Service;
use Extcode\Cart\Domain\Model\Cart\ServiceInterface;
use Extcode\Cart\Domain\Model\Order\BillingAddress;
use Extcode\Cart\Domain\Model\Order\Item as OrderItem;
use Extcode\Cart\Domain\Model\Order\Payment;
use Extcode\Cart\Domain\Model\Order\ShippingAddress;
use Extcode\Cart\Event\Order\PaymentEvent;
use Extcode\Cart\Service\PaymentMethodsServiceInterface;
use Extcode\Cart\Service\SessionHandler;
use Extcode\Cart\Utility\CartUtility;
use Extcode\CartPaypal\EventListener\Order\Payment\ClearCart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ClearCart::class)]
final class ClearCartTest extends UnitTestCase
{
    private const PAYMENT_ID = 2;

    private const SETTINGS = [
        'settings' => [
            'cart' => [
                'pid' => '3',
            ],
        ],
    ];

    private CartUtility&MockObject $cartUtility;

    private PaymentMethodsServiceInterface&MockObject $paymentMethodsService;

    private SessionHandler&MockObject $sessionHandler;

    private Cart $cart;

    private ClearCart $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartUtility = $this->createMock(CartUtility::class);
        $this->paymentMethodsService = $this->createMock(PaymentMethodsServiceInterface::class);
        $this->sessionHandler = $this->createMock(SessionHandler::class);

        $payment = self::createStub(ServiceInterface::class);
        $payment->method('getId')->willReturn(self::PAYMENT_ID);

        $this->cart = self::createStub(Cart::class);
        $this->cart->method('getPayment')->willReturn($payment);

        $this->subject = new ClearCart(
            $this->cartUtility,
            $this->paymentMethodsService,
            $this->sessionHandler,
        );
    }

    public static function otherProviderDataProvider(): \Generator
    {
        yield 'no provider' => [''];
        yield 'provider of another extension' => ['GIROSOLUTION_PAYPAL'];
        yield 'provider of another extension with clearing type' => ['GIROSOLUTION_CREDITCARD'];
    }

    #[Test]
    #[DataProvider('otherProviderDataProvider')]
    public function doesNothingForPaymentsOfOtherProviders(string $provider): void
    {
        $this->paymentMethodsService->expects(self::never())->method('getPaymentMethods');
        $this->cartUtility->expects(self::never())->method('getNewCart');
        $this->sessionHandler->expects(self::never())->method('writeCart');
        $this->sessionHandler->expects(self::never())->method('writeAddress');

        ($this->subject)($this->createEvent($provider));
    }

    #[Test]
    public function doesNotClearTheCartIfPreventClearCartIsSet(): void
    {
        $this->paymentMethodsService
            ->expects(self::once())
            ->method('getPaymentMethods')
            ->willReturn($this->createPaymentMethods([
                self::PAYMENT_ID => [
                    'preventClearCart' => '1',
                ],
            ]));

        $this->cartUtility->expects(self::never())->method('getNewCart');
        $this->sessionHandler->expects(self::never())->method('writeCart');
        $this->sessionHandler->expects(self::never())->method('writeAddress');

        ($this->subject)($this->createEvent('PAYPAL'));
    }

    public static function paymentMethodsWithoutPreventClearCartDataProvider(): \Generator
    {
        yield 'no payment methods' => [[]];
        yield 'preventClearCart not set' => [[self::PAYMENT_ID => []]];
        yield 'preventClearCart disabled' => [[self::PAYMENT_ID => ['preventClearCart' => '0']]];
        yield 'preventClearCart set for another payment' => [[self::PAYMENT_ID + 1 => ['preventClearCart' => '1']]];
    }

    /**
     * @param array<int, array> $paymentMethodConfigurations
     */
    #[Test]
    #[DataProvider('paymentMethodsWithoutPreventClearCartDataProvider')]
    public function clearsCartAndAddressesForPayPalPayments(array $paymentMethodConfigurations): void
    {
        $this->paymentMethodsService
            ->expects(self::once())
            ->method('getPaymentMethods')
            ->with($this->cart)
            ->willReturn($this->createPaymentMethods($paymentMethodConfigurations));

        $newCart = self::createStub(Cart::class);
        $this->cartUtility
            ->expects(self::once())
            ->method('getNewCart')
            ->with(self::SETTINGS)
            ->willReturn($newCart);

        $this->sessionHandler
            ->expects(self::once())
            ->method('writeCart')
            ->with('3', $newCart);

        $writtenAddresses = [];
        $this->sessionHandler
            ->expects(self::exactly(2))
            ->method('writeAddress')
            ->willReturnCallback(function (string $key, object $address) use (&$writtenAddresses): void {
                $writtenAddresses[$key] = $address::class;
            });

        ($this->subject)($this->createEvent('PAYPAL'));

        self::assertSame(
            [
                'billing_address_3' => BillingAddress::class,
                'shipping_address_3' => ShippingAddress::class,
            ],
            $writtenAddresses
        );
    }

    #[Test]
    public function clearsCartForCreditCardPaymentsWithPayPal(): void
    {
        $this->paymentMethodsService->expects(self::once())->method('getPaymentMethods')->willReturn([]);
        $this->cartUtility->expects(self::once())->method('getNewCart')->willReturn(self::createStub(Cart::class));

        $this->sessionHandler->expects(self::once())->method('writeCart');

        ($this->subject)($this->createEvent('PAYPAL_CREDITCARD'));
    }

    #[Test]
    public function doesNotStopThePropagationOfTheEvent(): void
    {
        $this->paymentMethodsService->expects(self::once())->method('getPaymentMethods')->willReturn([]);
        $this->cartUtility->expects(self::once())->method('getNewCart')->willReturn(self::createStub(Cart::class));
        $this->sessionHandler->expects(self::once())->method('writeCart');

        $event = $this->createEvent('PAYPAL');

        ($this->subject)($event);

        self::assertFalse($event->isPropagationStopped());
    }

    /**
     * Creates the payment methods like PaymentMethodsServiceInterface returns
     * them: services indexed by their id.
     *
     * @param array<int, array> $configurations
     * @return array<int, Service>
     */
    private function createPaymentMethods(array $configurations): array
    {
        $paymentMethods = [];
        foreach ($configurations as $id => $configuration) {
            $paymentMethods[$id] = new Service($id, $configuration);
        }

        return $paymentMethods;
    }

    private function createEvent(string $provider): PaymentEvent
    {
        $payment = new Payment();
        $payment->setProvider($provider);

        $orderItem = new OrderItem();
        $orderItem->setPayment($payment);

        return new PaymentEvent($this->cart, $orderItem, self::SETTINGS);
    }
}
