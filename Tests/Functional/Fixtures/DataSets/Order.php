<?php

declare(strict_types=1);

use Extcode\Cart\Domain\Model\Order\BillingAddress;

return [
    'tx_cart_domain_model_order_item' => [
        [
            'uid' => 1,
            'pid' => 2,
            'order_number' => 'ORD-2026-0001',
            'currency_code' => 'EUR',
            'currency_sign' => '€',
            'gross' => 111.05,
            'total_gross' => 119.0,
            'billing_address' => 1,
            'products' => 1,
            'shipping' => 1,
            'payment' => 1,
        ],
    ],
    'tx_cart_domain_model_order_address' => [
        [
            'uid' => 1,
            'pid' => 2,
            'item' => 1,
            'record_type' => '\\' . BillingAddress::class,
            'first_name' => 'Erika',
            'last_name' => 'Mustermann',
            'email' => 'erika@example.org',
            'street' => 'Hauptstraße 1',
            'zip' => '99084',
            'city' => 'Erfurt',
            'country' => 'de',
        ],
    ],
    'tx_cart_domain_model_order_product' => [
        [
            'uid' => 1,
            'pid' => 2,
            'item' => 1,
            'sku' => 'kanne',
            'title' => 'Kanne',
            'count' => 1,
            'price' => 111.05,
            'gross' => 111.05,
        ],
    ],
    'tx_cart_domain_model_order_shipping' => [
        [
            'uid' => 1,
            'pid' => 2,
            'item' => 1,
            'service_id' => 1,
            'name' => 'Standard',
            'gross' => 7.95,
        ],
    ],
    'tx_cart_domain_model_order_payment' => [
        [
            'uid' => 1,
            'pid' => 2,
            'item' => 1,
            'service_id' => 1,
            'name' => 'PayPal',
            'provider' => 'PAYPAL',
            'status' => 'open',
        ],
    ],
];
