<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

call_user_func(function () {
    $_LLL_db = 'LLL:EXT:cart_paypal/Resources/Private/Language/locallang_db.xlf:';

    ExtensionUtility::registerPlugin(
        'CartPaypal',
        'Payment',
        $_LLL_db . 'tx_cartpaypal.plugin.payment',
        'ext-cartpaypal-icon',
        'cart',
        $_LLL_db . 'tx_cartpaypal.plugin.payment.description'
    );
});
