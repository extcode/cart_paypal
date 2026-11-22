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
 * The payment providers of cart paid with PayPal, the value is the provider
 * of the payment method.
 */
enum PaymentProvider: string
{
    /**
     * The customer pays with the PayPal button.
     */
    case PayPal = 'PAYPAL';

    /**
     * The customer pays with a debit or credit card in an overlay of PayPal,
     * without a PayPal account.
     */
    case CreditCard = 'PAYPAL_CREDITCARD';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
