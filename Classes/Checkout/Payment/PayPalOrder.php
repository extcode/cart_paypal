<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

/**
 * An order created at PayPal.
 */
final readonly class PayPalOrder
{
    /**
     * @param string $approveUrl the page at PayPal the customer approves the payment on
     */
    public function __construct(
        public string $id,
        public string $approveUrl,
    ) {}
}
