<?php

$EM_CONF['cart_paypal'] = [
    'title' => 'Cart - PayPal',
    'description' => 'Shopping Cart(s) for TYPO3 - PayPal Payment Provider',
    'category' => 'services',
    'author' => 'Daniel Gohlke',
    'author_email' => 'ext.cart@extco.de',
    'author_company' => 'extco.de UG (haftungsbeschränkt)',
    'state' => 'beta',
    'version' => '5.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
            'cart' => '7.4.0',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
