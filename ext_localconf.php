<?php

defined('TYPO3_MODE') or die();

// configure plugins

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'CartPaypal',
    'Cart',
    [
        \Extcode\CartPaypal\Controller\Order\PaymentController::class => 'success, cancel, notify',
    ],
    [
        \Extcode\CartPaypal\Controller\Order\PaymentController::class => 'success, cancel, notify',
    ]
);
