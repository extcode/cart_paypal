<?php

declare(strict_types=1);

defined('TYPO3') or die();

use Extcode\CartPaypal\Controller\ApiController;
use Extcode\CartPaypal\Controller\PaymentController;
use Extcode\CartPaypal\Controller\WebhookController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

// configure plugins

// The content element on the payment page, shows the PayPal button.
ExtensionUtility::configurePlugin(
    'CartPaypal',
    'Payment',
    [
        PaymentController::class => 'show',
    ],
    [
        PaymentController::class => 'show',
    ]
);

// The endpoints on the page type, called by the JavaScript, by PayPal and by
// the customer returning from PayPal. The webhook is the default action, so
// its URL needs no plugin arguments and no cHash.
ExtensionUtility::configurePlugin(
    'CartPaypal',
    'Api',
    [
        WebhookController::class => 'webhook',
        ApiController::class => 'createOrder, captureOrder, redirect, return, cancel',
    ],
    [
        WebhookController::class => 'webhook',
        ApiController::class => 'createOrder, captureOrder, redirect, return, cancel',
    ]
);

// exclude parameters from cHash, PayPal appends them to the return URL

$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'token';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'PayerID';
