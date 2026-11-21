<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

enum CaptureResult: string
{
    /**
     * The money has been received, the order is finished.
     */
    case Paid = 'paid';

    /**
     * PayPal has not received the money yet, e.g. it is reviewed. A webhook
     * finishes the order later.
     */
    case Pending = 'pending';

    /**
     * The payment was declined, the customer may try again.
     */
    case Failed = 'failed';
}
