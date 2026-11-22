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
 * The status of the payment of an order, as used by cart.
 */
final class PaymentStatus
{
    public const OPEN = 'open';

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const CANCELED = 'canceled';
}
