<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Unit\Checkout\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Order\Item;
use Extcode\CartPaypal\Checkout\Payment\OrderRequestBuilder;
use Extcode\CartPaypal\Checkout\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(OrderRequestBuilder::class)]
#[CoversClass(Settings::class)]
final class OrderRequestBuilderTest extends UnitTestCase
{
    #[Test]
    public function buildsAnOrderWithTheTotalOfTheOrder(): void
    {
        $subject = $this->createSubject([]);

        self::assertSame(
            [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'custom_id' => '7',
                        'amount' => [
                            'currency_code' => 'EUR',
                            'value' => '119.90',
                        ],
                        'invoice_id' => 'ORD-2026-0001',
                    ],
                ],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'return_url' => 'https://example.org/return',
                            'cancel_url' => 'https://example.org/cancel',
                            'user_action' => 'PAY_NOW',
                            'shipping_preference' => 'NO_SHIPPING',
                        ],
                    ],
                ],
            ],
            $subject->build($this->createOrderItem(), 'https://example.org/return', 'https://example.org/cancel')
        );
    }

    #[Test]
    public function buildsAnOrderWithoutPaymentSourceForTheCardButton(): void
    {
        $subject = $this->createSubject(['brandName' => 'Example Shop', 'locale' => 'de-DE']);

        self::assertSame(
            [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'custom_id' => '7',
                        'amount' => [
                            'currency_code' => 'EUR',
                            'value' => '119.90',
                        ],
                        'invoice_id' => 'ORD-2026-0001',
                    ],
                ],
            ],
            $subject->buildForCard($this->createOrderItem())
        );
    }

    #[Test]
    public function addsTheConfiguredExperienceContext(): void
    {
        $subject = $this->createSubject([
            'brandName' => 'Example Shop',
            'locale' => 'de-DE',
            'shippingPreference' => 'GET_FROM_FILE',
        ]);

        $order = $subject->build($this->createOrderItem(), 'https://example.org/return', 'https://example.org/cancel');

        self::assertSame(
            [
                'return_url' => 'https://example.org/return',
                'cancel_url' => 'https://example.org/cancel',
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'GET_FROM_FILE',
                'brand_name' => 'Example Shop',
                'locale' => 'de-DE',
            ],
            $order['payment_source']['paypal']['experience_context']
        );
    }

    #[Test]
    public function shortensTheBrandNameToTheMaximumLength(): void
    {
        $subject = $this->createSubject(['brandName' => str_repeat('ä', 200)]);

        $order = $subject->build($this->createOrderItem(), 'https://example.org/return', 'https://example.org/cancel');

        self::assertSame(127, mb_strlen($order['payment_source']['paypal']['experience_context']['brand_name']));
    }

    #[Test]
    public function omitsTheInvoiceIdWithoutOrderNumber(): void
    {
        $orderItem = new Item();
        $orderItem->_setProperty('uid', 7);
        $orderItem->setCurrencyCode('EUR');
        $orderItem->setTotalGross(119.9);

        $order = $this->createSubject([])->build($orderItem, 'https://example.org/return', 'https://example.org/cancel');

        self::assertArrayNotHasKey('invoice_id', $order['purchase_units'][0]);
    }

    public static function amountDataProvider(): \Generator
    {
        yield 'two decimals' => [119.9, 'EUR', '119.90'];
        yield 'rounded' => [10.005, 'EUR', '10.01'];
        yield 'no thousands separator' => [1234.5, 'USD', '1234.50'];
        yield 'lower case currency' => [5.0, 'eur', '5.00'];
        yield 'currency without decimals' => [1234.56, 'JPY', '1235'];
        yield 'forint without decimals' => [99.4, 'HUF', '99'];
    }

    #[Test]
    #[DataProvider('amountDataProvider')]
    public function formatsTheAmountForTheCurrency(float $amount, string $currencyCode, string $expected): void
    {
        self::assertSame($expected, OrderRequestBuilder::formatAmount($amount, $currencyCode));
    }

    private function createOrderItem(): Item
    {
        $orderItem = new Item();
        $orderItem->_setProperty('uid', 7);
        $orderItem->setOrderNumber('ORD-2026-0001');
        $orderItem->setCurrencyCode('eur');
        $orderItem->setTotalGross(119.9);

        return $orderItem;
    }

    private function createSubject(array $settings): OrderRequestBuilder
    {
        $configurationManager = self::createStub(ConfigurationManagerInterface::class);
        $configurationManager->method('getConfiguration')->willReturn(['settings' => $settings]);

        return new OrderRequestBuilder(new Settings($configurationManager));
    }
}
