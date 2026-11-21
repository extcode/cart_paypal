<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Unit\Event\Order;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart\Cart;
use Extcode\Cart\Domain\Model\Order\Item as OrderItem;
use Extcode\Cart\Event\Order\EventInterface;
use Extcode\CartPaypal\Event\Order\CancelEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\StoppableEventInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(CancelEvent::class)]
final class CancelEventTest extends UnitTestCase
{
    private Cart $cart;

    private OrderItem $orderItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cart = self::createStub(Cart::class);
        $this->orderItem = new OrderItem();
    }

    #[Test]
    public function implementsTheOrderEventInterfaces(): void
    {
        $subject = new CancelEvent($this->cart, $this->orderItem);

        self::assertInstanceOf(EventInterface::class, $subject);
        self::assertInstanceOf(StoppableEventInterface::class, $subject);
    }

    #[Test]
    public function returnsGivenCartOrderItemAndSettings(): void
    {
        $settings = [
            'settings' => [
                'cart' => [
                    'pid' => '3',
                ],
            ],
        ];

        $subject = new CancelEvent($this->cart, $this->orderItem, $settings);

        self::assertSame($this->cart, $subject->getCart());
        self::assertSame($this->orderItem, $subject->getOrderItem());
        self::assertSame($settings, $subject->getSettings());
    }

    #[Test]
    public function settingsAreEmptyByDefault(): void
    {
        $subject = new CancelEvent($this->cart, $this->orderItem);

        self::assertSame([], $subject->getSettings());
    }

    #[Test]
    public function propagationIsNotStoppedByDefault(): void
    {
        $subject = new CancelEvent($this->cart, $this->orderItem);

        self::assertFalse($subject->isPropagationStopped());
    }

    #[Test]
    public function propagationCanBeStoppedAndResumed(): void
    {
        $subject = new CancelEvent($this->cart, $this->orderItem);

        $subject->setPropagationStopped(true);
        self::assertTrue($subject->isPropagationStopped());

        $subject->setPropagationStopped(false);
        self::assertFalse($subject->isPropagationStopped());
    }
}
