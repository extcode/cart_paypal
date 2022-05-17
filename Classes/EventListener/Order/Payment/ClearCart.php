<?php
declare(strict_types=1);
namespace Extcode\CartPaypal\EventListener\Order\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Event\Order\EventInterface;
use Extcode\Cart\Service\SessionHandler;
use Extcode\Cart\Utility\CartUtility;
use Extcode\Cart\Utility\ParserUtility;

class ClearCart extends \Extcode\Cart\EventListener\Order\Finish\ClearCart
{
    public function __construct(
        CartUtility $cartUtility,
        ParserUtility $parserUtility,
        SessionHandler $sessionHandler
    ) {
        parent::__construct($cartUtility, $parserUtility, $sessionHandler);
    }

    public function __invoke(EventInterface $event): void
    {
        $orderItem = $event->getOrderItem();

        $provider = $orderItem->getPayment()->getProvider();

        if (strpos($provider, 'PAYPAL') === 0) {
            parent::__invoke($event);
        }
    }
}
