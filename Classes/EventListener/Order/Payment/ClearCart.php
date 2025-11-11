<?php
declare(strict_types=1);
namespace Extcode\CartPaypal\EventListener\Order\Payment;

use Extcode\Cart\Event\Order\EventInterface;
use Extcode\Cart\Service\SessionHandler;
use Extcode\Cart\Utility\CartUtility;
use Extcode\Cart\Service\PaymentMethodsServiceInterface; // Neu hinzugefügt

class ClearCart extends \Extcode\Cart\EventListener\Order\Finish\ClearCart
{
    public function __construct(
        CartUtility $cartUtility,
        PaymentMethodsServiceInterface $paymentMethodsService, // Geändert!
        SessionHandler $sessionHandler
    ) {
        parent::__construct($cartUtility, $paymentMethodsService, $sessionHandler);
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