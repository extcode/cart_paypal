<?php

defined('TYPO3_MODE') or die();

// configure plugins

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Extcode.cart_paypal',
    'Cart',
    [
        'Order\Payment' => 'success, cancel',
    ],
    // non-cacheable actions
    [
        'Order\Payment' => 'success, cancel',
    ]
);

// configure signal slots

$dispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
$dispatcher->connect(
    \Extcode\Cart\Utility\PaymentUtility::class,
    'handlePayment',
    \Extcode\CartPaypal\Utility\PaymentUtility::class,
    'handlePayment'
);

// configure eid dispatcher

if (TYPO3_MODE === 'FE') {
    $TYPO3_CONF_VARS['FE']['eID_include']['paypal-payment-api'] = \Extcode\CartPaypal\Utility\PaymentProcess::class . '::process';
}
