<?php

declare(strict_types=1);

defined('TYPO3') or die();

use Extcode\PaypalTest\Controller\PaymentEventController;
use Extcode\PaypalTest\Http\PayPalApiMock;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::configurePlugin(
    'PaypalTest',
    'PaymentEvent',
    [
        PaymentEventController::class => 'dispatch',
    ],
    [
        PaymentEventController::class => 'dispatch',
    ]
);

// No request to PayPal leaves the tests.
$GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler']['paypal-api-mock'] = PayPalApiMock::middleware();
