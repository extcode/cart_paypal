<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Domain\Repository\PageRepository;

return [
    'pages' => [
        [
            'uid' => 1,
            'pid' => 0,
            'title' => 'Cart',
            'doktype' => PageRepository::DOKTYPE_DEFAULT,
            'slug' => '/',
            'is_siteroot' => 1,
        ],
        [
            'uid' => 2,
            'pid' => 1,
            'title' => 'Orders',
            'doktype' => PageRepository::DOKTYPE_SYSFOLDER,
            'slug' => '/orders',
        ],
        [
            'uid' => 3,
            'pid' => 1,
            'title' => 'Payment',
            'doktype' => PageRepository::DOKTYPE_DEFAULT,
            'slug' => '/payment',
        ],
    ],
    'sys_template' => [
        [
            'uid' => 1,
            'pid' => 1,
            'title' => 'Root',
            'root' => 1,
            'clear' => 3,
            'include_static_file' => implode(',', [
                'EXT:cart/Configuration/TypoScript',
                'EXT:cart_paypal/Configuration/TypoScript',
            ]),
            'constants' => implode(PHP_EOL, [
                'plugin.tx_cart.settings.cart.pid = 1',
                'plugin.tx_cart.settings.order.pid = 2',
                'plugin.tx_cartpaypal.paymentPid = 3',
            ]),
            'config' => implode(PHP_EOL, [
                // the payment page, renders the plugin of cart_paypal only
                'page = PAGE',
                'page {',
                '    10 = EXTBASEPLUGIN',
                '    10 {',
                '        extensionName = CartPaypal',
                '        pluginName = Payment',
                '    }',
                '}',
                // dispatches the PaymentEvent like cart after storing the order
                'paymentEvent = PAGE',
                'paymentEvent {',
                '    typeNum = 4711',
                '    config.disableAllHeaderCode = 1',
                '    10 = EXTBASEPLUGIN',
                '    10 {',
                '        extensionName = PaypalTest',
                '        pluginName = PaymentEvent',
                '    }',
                '}',
            ]),
        ],
    ],
];
