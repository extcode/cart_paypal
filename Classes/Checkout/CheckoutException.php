<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use RuntimeException;

/**
 * The order can not be paid with PayPal (anymore), e.g. it is paid already
 * or its payment is pending.
 */
final class CheckoutException extends RuntimeException
{
    public const PAID_OR_PENDING = 1795600101;

    public const NOT_PAYPAL = 1795600102;

    public const UNKNOWN_PAYPAL_ORDER = 1795600103;

    public const NO_ORDER = 1795600104;

    public const CANCELED = 1795600105;
}
