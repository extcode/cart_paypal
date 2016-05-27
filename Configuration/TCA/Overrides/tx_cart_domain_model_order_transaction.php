<?php

defined('TYPO3_MODE') or die();

$_LLL = 'LLL:EXT:cart_paypal/Resources/Private/Language/locallang_db.xlf';

$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.completed', 'completed'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.created', 'created'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.denied', 'denied'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.expired', 'expired'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.failed', 'failed'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.refunded', 'refunded'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.reversed', 'reversed'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.processed', 'processed'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.voided', 'voided'];
