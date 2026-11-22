<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Order\Item;
use Extcode\CartPaypal\Checkout\Settings;

/**
 * Builds the body to create an order with the Orders v2 API of PayPal.
 *
 * Only the total amount of the stored order is sent. A breakdown into items,
 * shipping and discounts has to add up exactly, otherwise PayPal rejects the
 * order; the total is always correct.
 */
final class OrderRequestBuilder
{
    /**
     * Currencies PayPal accepts without decimals.
     */
    private const CURRENCIES_WITHOUT_DECIMALS = ['HUF', 'JPY', 'TWD'];

    private const MAX_LENGTH = 127;

    public function __construct(
        private readonly Settings $settings,
    ) {}

    /**
     * The order for the PayPal button and for the redirect to PayPal, PayPal
     * returns the customer to the given URLs.
     */
    public function build(Item $orderItem, string $returnUrl, string $cancelUrl): array
    {
        $experienceContext = [
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'user_action' => 'PAY_NOW',
            'shipping_preference' => $this->settings->getShippingPreference(),
        ];
        if ($this->settings->getBrandName() !== '') {
            $experienceContext['brand_name'] = mb_substr($this->settings->getBrandName(), 0, self::MAX_LENGTH);
        }
        if ($this->settings->getLocale() !== '') {
            $experienceContext['locale'] = $this->settings->getLocale();
        }

        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                $this->buildPurchaseUnit($orderItem),
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => $experienceContext,
                ],
            ],
        ];
    }

    /**
     * The order for the card button of the JavaScript SDK. The customer enters
     * the card in an overlay of PayPal, the order has no payment source.
     */
    public function buildForCard(Item $orderItem): array
    {
        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                $this->buildPurchaseUnit($orderItem),
            ],
        ];
    }

    public static function formatAmount(float $amount, string $currencyCode): string
    {
        $decimals = in_array(strtoupper($currencyCode), self::CURRENCIES_WITHOUT_DECIMALS, true) ? 0 : 2;

        return number_format(round($amount, $decimals), $decimals, '.', '');
    }

    private function buildPurchaseUnit(Item $orderItem): array
    {
        $currencyCode = strtoupper($orderItem->getCurrencyCode());

        $purchaseUnit = [
            'custom_id' => (string)$orderItem->getUid(),
            'amount' => [
                'currency_code' => $currencyCode,
                'value' => self::formatAmount($orderItem->getTotalGross(), $currencyCode),
            ],
        ];

        $orderNumber = (string)$orderItem->getOrderNumber();
        if ($orderNumber !== '') {
            $purchaseUnit['invoice_id'] = mb_substr($orderNumber, 0, self::MAX_LENGTH);
        }

        return $purchaseUnit;
    }
}
