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
 * The status of a transaction, one transaction is stored per PayPal order.
 * The status of PayPal is stored as external status code.
 */
enum TransactionStatus: string
{
    case Created = 'created';

    case Pending = 'pending';

    case Paid = 'paid';

    case Failed = 'failed';

    case Canceled = 'canceled';
}
