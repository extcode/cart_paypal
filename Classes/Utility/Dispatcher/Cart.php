<?php

namespace Extcode\CartPaypal\Utility\Dispatcher;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Ajax Dispatcher
 *
 * @author Daniel Lorenz <ext.cart@extco.de>
 */
class Cart
{
    /**
     * Object Manager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Configuration Manager
     *
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * Persistence Manager
     *
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     */
    protected $persistenceManager;

    /**
     * Request
     *
     * @var \TYPO3\CMS\Extbase\Mvc\Request
     */
    protected $request;

    /**
     * logManager
     *
     * @var \TYPO3\CMS\Core\Log\LogManager
     */
    protected $logManager;

    /**
     * Cart Repository
     *
     * @var \Extcode\Cart\Domain\Repository\CartRepository
     */
    protected $cartRepository;

    /**
     * Order Item Repository
     *
     * @var \Extcode\Cart\Domain\Repository\Order\ItemRepository
     * @inject
     */
    protected $orderItemRepository;

    /**
     * Order Payment Repository
     *
     * @var \Extcode\Cart\Domain\Repository\Order\PaymentRepository
     */
    protected $orderPaymentRepository;

    /**
     * Order Payment Repository
     *
     * @var \Extcode\Cart\Domain\Repository\Order\TransactionRepository
     */
    protected $orderTransactionRepository;

    /**
     * Cart
     *
     * @var \Extcode\Cart\Domain\Model\Cart
     */
    protected $cart;

    /**
     * OrderItem
     *
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem;

    /**
     * Order Payment
     *
     * @var \Extcode\Cart\Domain\Model\Order\Payment
     */
    protected $orderPayment;

    /**
     * Order Transaction
     *
     * @var \Extcode\Cart\Domain\Model\Order\Transaction
     */
    protected $transaction;

    /**
     * @var \TYPO3\CMS\Extbase\Service\TypoScriptService
     */
    protected $typoScriptService;

    /**
     * Order Number
     *
     * @var string
     */
    protected $orderNumber;

    /**
     * @var array
     */
    protected $arguments = [];

    /**
     * @var int
     */
    protected $pageUid;

    /**
     * @var array
     */
    protected $conf = [];

    /**
     * Cart Configuration
     *
     * @var array
     */
    protected $cartConf = [];

    /**
     * Cart Paypal Configuration
     *
     * @var array
     */
    protected $cartPaypalConf = [];

    /**
     * Curl Result
     *
     * @var string
     */
    protected $curlResult = '';

    /**
     * Curl Results
     *
     * @var array
     */
    protected $curlResults = [];

    /**
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(
        \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager
     */
    public function injectConfigurationManager(
        \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager
    ) {
        $this->cartPaypalConfigurationManager = $configurationManager;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
     */
    public function injectPersistenceManager(
        \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
    ) {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * @param \TYPO3\CMS\Core\Log\LogManager $logManager
     */
    public function injectLogManager(
        \TYPO3\CMS\Core\Log\LogManager $logManager
    ) {
        $this->logManager = $logManager;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\CartRepository $cartRepository
     */
    public function injectCartRepository(
        \Extcode\Cart\Domain\Repository\CartRepository $cartRepository
    ) {
        $this->cartRepository = $cartRepository;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\Order\ItemRepository $orderItemRepository
     */
    public function injectOrderItemRepository(
        \Extcode\Cart\Domain\Repository\Order\ItemRepository $orderItemRepository
    ) {
        $this->orderItemRepository = $orderItemRepository;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\Order\PaymentRepository $orderPaymentRepository
     */
    public function injectOrderPaymentRepository(
        \Extcode\Cart\Domain\Repository\Order\PaymentRepository $orderPaymentRepository
    ) {
        $this->orderPaymentRepository = $orderPaymentRepository;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\Order\TransactionRepository $orderTransactionRepository
     */
    public function injectOrderTransactionRepository(
        \Extcode\Cart\Domain\Repository\Order\TransactionRepository $orderTransactionRepository
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Service\TypoScriptService $typoScriptService
     */
    public function injectTypoScriptService(
        \TYPO3\CMS\Extbase\Service\TypoScriptService $typoScriptService
    ) {
        $this->typoScriptService = $typoScriptService;
    }

    /**
     * Initialize Settings
     */
    protected function initSettings()
    {
        $this->cartConf = $this->typoScriptService->convertTypoScriptArrayToPlainArray(
            $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_cart.']
        );
        $this->cartPaypalConf = $this->typoScriptService->convertTypoScriptArrayToPlainArray(
            $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_cartpaypal.']
        );
    }

    /**
     * Get Request
     */
    protected function getRequest()
    {
        $request = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('request');
        $action = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('eID');

        $this->request = $this->objectManager->get(
            \TYPO3\CMS\Extbase\Mvc\Request::class
        );
        $this->request->setControllerVendorName('Extcode');
        $this->request->setControllerExtensionName('CartPaypal');
        $this->request->setControllerActionName($action);
        if (is_array($request['arguments'])) {
            $this->request->setArguments($request['arguments']);
        }
    }

    /**
     * Dispatch
     */
    public function dispatch()
    {
        $response = [];

        $this->initSettings();

        $this->getRequest();

        switch ($this->request->getControllerActionName()) {
            case 'testIpn':
                $response = $this->testIpnAction();
                break;
            case 'processIpn':
                $response = $this->processIpnAction();
                break;
        }

        return json_encode($response);
    }

    /**
     * Test IPN Action
     *
     * @return array
     */
    protected function testIpnAction()
    {
        $response = [
            'sandbox' => $this->cartPaypalConf['settings']['sandbox'] ? true : false,
            'business' => $this->cartPaypalConf['settings']['business'] ? true : false,
            'notify_url' => $this->cartPaypalConf['settings']['notify_url'] ? true : false,
            'return_url' => $this->cartPaypalConf['settings']['return_url'] ? true : false,
            'cancel_url' => $this->cartPaypalConf['settings']['cancel_url'] ? true : false,
        ];

        return $response;
    }

    /**
     * Process IPN Action
     *
     * @return array
     */
    protected function processIpnAction()
    {
        $logger = $this->logManager->getLogger(__CLASS__);

        $rawPostData = file_get_contents('php://input');

        $logger->debug(
            'IPN request',
            [
                'rawPostData' => $rawPostData,
            ]
        );

        $curlRequest = $this->getCurlRequestFromPostData($rawPostData);

        $curlStatus = $this->execCurlRequest($curlRequest);

        parse_str($rawPostData, $this->curlResults);

        $this->getOrderItem();
        $this->getOrderPayment();
        $this->getCart();
        $this->addOrderTransaction($this->curlResults['txn_id'], $rawPostData);

        if (strcmp($this->curlResult, 'verified') == 0) {
            $paymentStatus = strtolower($this->curlResults['payment_status']);

            switch ($paymentStatus) {
                case 'completed':
                    $this->orderPayment->setStatus('paid');
                    break;
                default:
                    $this->orderPayment->setStatus($paymentStatus);
            }

            $this->transaction->setStatus($paymentStatus);
            if ($paymentStatus == 'pending') {
                $this->transaction->setNote($this->curlResults['pending_reason']);
            }
        } elseif (strcmp($this->curlResult, 'invalid') == 0) {
            \TYPO3\CMS\Core\Utility\GeneralUtility::sysLog('INVALID', 'cart_paypal', 3);

            $this->transaction->setStatus('invalid');
        }

        $this->orderTransactionRepository->update($this->transaction);
        $this->orderPaymentRepository->update($this->orderPayment);

        $this->persistenceManager->persistAll();

        $this->sendMails();

        $this->updateCart();

        return [];
    }

    /**
     * Send Mails
     */
    protected function sendMails()
    {
        $billingAddress = $this->orderItem->getBillingAddress()->_loadRealInstance();
        if ($this->orderItem->getShippingAddress()) {
            $shippingAddress = $this->orderItem->getShippingAddress()->_loadRealInstance();
        }

        $this->sendBuyerMail($this->orderItem, $billingAddress, $shippingAddress);
        $this->sendSellerMail($this->orderItem, $billingAddress, $shippingAddress);
    }

    /**
     * Send a Mail to Buyer
     *
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem Order Item
     * @param \Extcode\Cart\Domain\Model\Order\Address $billingAddress Billing Address
     * @param \Extcode\Cart\Domain\Model\Order\Address $shippingAddress Shipping Address
     */
    protected function sendBuyerMail(
        \Extcode\Cart\Domain\Model\Order\Item $orderItem,
        \Extcode\Cart\Domain\Model\Order\Address $billingAddress,
        \Extcode\Cart\Domain\Model\Order\Address $shippingAddress = null
    ) {
        $mailHandler = $this->objectManager->get(
            \Extcode\Cart\Service\MailHandler::class
        );

        $mailHandler->setCart($this->cart->getCart());

        $mailHandler->sendBuyerMail($orderItem, $billingAddress, $shippingAddress);
    }

    /**
     * Send a Mail to Seller
     *
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem Order Item
     * @param \Extcode\Cart\Domain\Model\Order\Address $billingAddress Billing Address
     * @param \Extcode\Cart\Domain\Model\Order\Address $shippingAddress Shipping Address
     */
    protected function sendSellerMail(
        \Extcode\Cart\Domain\Model\Order\Item $orderItem,
        \Extcode\Cart\Domain\Model\Order\Address $billingAddress,
        \Extcode\Cart\Domain\Model\Order\Address $shippingAddress = null
    ) {
        $mailHandler = $this->objectManager->get(
            \Extcode\Cart\Service\MailHandler::class
        );

        $mailHandler->setCart($this->cart->getCart());

        $mailHandler->sendSellerMail($orderItem, $billingAddress, $shippingAddress);
    }

    /**
     * Parse Raw Post Data
     *
     * @param $rawPostData
     * @return array
     */
    protected function parseRawPostData($rawPostData)
    {
        $rawPostDataArray = explode('&', $rawPostData);

        $parsedPostDataArray = [];
        foreach ($rawPostDataArray as $keyval) {
            $keyval = explode('=', $keyval);
            if (count($keyval) == 2) {
                $parsedPostDataArray[$keyval[0]] = urldecode($keyval[1]);
            }
        }

        return $parsedPostDataArray;
    }

    /**
     * Get Curl Request From Post Data
     *
     * @param string $rawPostData
     * @return string
     */
    protected function getCurlRequestFromPostData($rawPostData)
    {
        $parsePostData = $this->parseRawPostData($rawPostData);

        $curlRequest = 'cmd=_notify-validate';
        if (function_exists('get_magic_quotes_gpc')) {
            $get_magic_quotes_exists = true;
        }
        foreach ($parsePostData as $key => $value) {
            if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
                $value = urlencode(stripslashes($value));
            } else {
                $value = urlencode($value);
            }
            $curlRequest .= "&$key=$value";

            if ($key == 'invoice') {
                $this->orderNumber = $value;
            }
        }

        return $curlRequest;
    }

    /**
     * Execute Curl Request
     *
     * @param string $curlRequest
     *
     * @return bool
     */
    protected function execCurlRequest($curlRequest)
    {
        $paypalUrl = $this->getPaypalUrl();

        $ch = curl_init($paypalUrl);
        if ($ch == false) {
            return false;
        }

        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlRequest);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);

        if (is_array($this->cartPaypalConf) && intval($this->cartPaypalConf['curl_timeout'])) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, intval($this->cartPaypalConf['curl_timeout']));
        } else {
            // Set TCP timeout to 300 seconds
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Connection: Close']);

        $this->curlResult = strtolower(curl_exec($ch));
        $curlError = curl_errno($ch);

        if ($curlError != 0) {
            if (TYPO3_DLOG) {
                $msgArray = [
                    'curl_error' => curl_error($ch),
                    'curl_request' => $curlRequest,
                    'curl_result' => $this->curlResult
                ];
                \TYPO3\CMS\Core\Utility\GeneralUtility::devLog(
                    'Can\'t connect to PayPal to validate IPN message',
                    'cart_paypal',
                    0,
                    $msgArray
                );
            }
            curl_close($ch);
            exit;
        } else {
            if (TYPO3_DLOG) {
                $msgArray = [
                    'curl_info' => curl_getinfo($ch, CURLINFO_HEADER_OUT),
                    'curl_request' => $curlRequest,
                    'curl_result' => $this->curlResult
                ];
                \TYPO3\CMS\Core\Utility\GeneralUtility::devLog(
                    'Can connect to PayPal to validate IPN message',
                    'cart_paypal',
                    0,
                    $msgArray
                );

                $this->curlResults = explode("\r\n\r\n", $this->curlResult);
            }
            curl_close($ch);
        }

        return true;
    }

    /**
     * Returns Paypal Url
     *
     * @return string
     */
    protected function getPaypalUrl()
    {
        if ($this->cartPaypalConf['settings']['sandbox']) {
            $paypalUrl = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        } else {
            $paypalUrl = 'https://www.paypal.com/cgi-bin/webscr';
        }

        return $paypalUrl;
    }

    /**
     * Get Order Item
     */
    protected function getOrderItem()
    {
        if ($this->orderNumber) {
            $this->orderItem = $this->orderItemRepository->findOneByOrderNumber($this->orderNumber);
        }
    }

    /**
     * Get Payment
     */
    protected function getOrderPayment()
    {
        if ($this->orderItem) {
            $this->orderPayment = $this->orderItem->getPayment();
        }
    }

    /**
     * Get Cart
     */
    protected function getCart()
    {
        if ($this->orderItem) {
            /** @var $querySettings \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings */
            $querySettings = $this->objectManager->get(
                \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class
            );
            $querySettings->setStoragePageIds([$this->cartConf['settings']['order']['pid']]);
            $this->cartRepository->setDefaultQuerySettings($querySettings);

            $this->cart = $this->cartRepository->findOneByOrderItem($this->orderItem);
        }
    }

    /**
     * Update Cart
     */
    protected function updateCart()
    {
        $this->cart->setWasOrdered(true);

        $this->cartRepository->update($this->cart);

        $this->persistenceManager->persistAll();
    }

    /**
     * @param string $txn_id
     * @param string $txn_txt
     */
    protected function addOrderTransaction($txn_id, $txn_txt = '')
    {
        $this->transaction = $this->objectManager->get('Extcode\Cart\Domain\Model\Order\Transaction');
        $this->transaction->setPid($this->orderPayment->getPid());

        $this->transaction->setTxnId($txn_id);
        $this->transaction->setTxnTxt($txn_txt);
        $this->orderTransactionRepository->add($this->transaction);

        if ($this->orderPayment) {
            $this->orderPayment->addTransaction($this->transaction);
        }

        $this->orderPaymentRepository->update($this->orderPayment);

        $this->persistenceManager->persistAll();
    }

    /**
     * @param int $status
     */
    protected function setOrderPaymentStatus($status)
    {
        if ($this->orderPayment) {
            $this->orderPayment->setStatus($status);
        }
    }
}
