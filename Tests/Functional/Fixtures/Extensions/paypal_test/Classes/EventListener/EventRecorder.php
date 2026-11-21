<?php

declare(strict_types=1);

namespace Extcode\PaypalTest\EventListener;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

/**
 * Records the dispatched events, registered as event listener by the test
 * extension paypal_test.
 *
 * The events are stored statically, as frontend requests in functional tests
 * use their own instance of the listener.
 */
final class EventRecorder
{
    /**
     * @var array<class-string, object[]>
     */
    private static array $events = [];

    public function __invoke(object $event): void
    {
        self::$events[$event::class][] = $event;
    }

    /**
     * @return array<class-string, object[]>
     */
    public static function getEvents(): array
    {
        return self::$events;
    }

    public static function reset(): void
    {
        self::$events = [];
    }
}
