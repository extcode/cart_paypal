<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout\Cart;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Service\SessionHandler;
use Extcode\CartPaypal\Checkout\Settings;

/**
 * Puts the cart of a canceled payment back into the session, so the customer
 * may choose another payment method.
 */
final class CartRestorer
{
    public function __construct(
        private readonly SessionHandler $sessionHandler,
        private readonly Settings $settings,
    ) {}

    public function restore(Cart $cartRecord): void
    {
        $cart = $cartRecord->getCart();
        if ($cart === null) {
            return;
        }

        // a new order gets new numbers
        $cart->resetOrderNumber();
        $cart->resetInvoiceNumber();
        $this->sessionHandler->writeCart((string)$this->settings->getCartPid(), $cart);
    }
}
