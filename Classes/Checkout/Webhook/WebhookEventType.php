<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout\Webhook;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

/**
 * The webhook events of PayPal processed by the extension, all others are
 * ignored.
 */
enum WebhookEventType: string
{
    /**
     * The customer approved the payment but did not return, e.g. the browser
     * was closed. The resource is the PayPal order.
     */
    case OrderApproved = 'CHECKOUT.ORDER.APPROVED';

    /**
     * The resource of the capture events is the capture, it refers to the
     * PayPal order.
     */
    case CaptureCompleted = 'PAYMENT.CAPTURE.COMPLETED';

    case CapturePending = 'PAYMENT.CAPTURE.PENDING';

    case CaptureDenied = 'PAYMENT.CAPTURE.DENIED';

    case CaptureDeclined = 'PAYMENT.CAPTURE.DECLINED';
}
