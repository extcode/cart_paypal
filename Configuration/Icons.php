<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'ext-cartpaypal-icon' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:cart_paypal/Resources/Public/Icons/Extension.svg',
    ],
];
