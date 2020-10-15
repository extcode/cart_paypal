<?php

$EM_CONF['cart_paypal'] = [
    'title' => 'Cart - PayPal',
    'description' => 'Shopping Cart(s) for TYPO3 - PayPal Payment Provider',
    'category' => 'services',
    'author' => 'Daniel Gohlke',
    'author_email' => 'ext.cart@extco.de',
    'author_company' => 'extco.de UG (haftungsbeschränkt)',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'beta',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '4.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-9.5.99',
            'cart' => '6.3.0',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
