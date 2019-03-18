<?php

namespace Extcode\CartPaypal\Utility;

use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class PaymentProcess
{
    const PAYPAL_API_SANDBOX = 'https://www.sandbox.paypal.com/webscr?';
    const PAYPAL_API_LIVE = 'https://www.paypal.com/webscr?';

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var TypoScriptService
     */
    protected $typoScriptService;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var CartRepository
     */
    protected $cartRepository;

    /**
     * @var Order\ItemRepository
     */
    protected $orderItemRepository;

    /**
     * @var Order\PaymentRepository
     */
    protected $orderPaymentRepository;

    /**
     * @var Order\TransactionRepository
     */
    protected $orderTransactionRepository;

    /**
     * @var \Extcode\Cart\Domain\Model\Cart
     */
    protected $cart;

    /**
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem;

    /**
     * @var \Extcode\Cart\Domain\Model\Order\Payment
     */
    protected $orderPayment;

    /**
     * @var \Extcode\Cart\Domain\Model\Order\Transaction
     */
    protected $orderTransaction;

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
     * @var array
     */
    protected $cartConf = [];

    /**
     * @var string
     */
    protected $curlResult = '';

    /**
     * @var array
     */
    protected $curlResults = [];

    public function __construct()
    {
        $this->objectManager = GeneralUtility::makeInstance(
            ObjectManager::class
        );
        $this->persistenceManager = $this->objectManager->get(
            PersistenceManager::class
        );
        $this->typoScriptService = $this->objectManager->get(
            TypoScriptService::class
        );

        $this->logger = $this->objectManager->get(
            LogManager::class
        )->getLogger(__CLASS__);

        $this->orderItemRepository = $this->objectManager->get(
            Order\ItemRepository::class
        );
        $this->orderPaymentRepository = $this->objectManager->get(
            Order\PaymentRepository::class
        );
        $this->orderTransactionRepository = $this->objectManager->get(
            Order\TransactionRepository::class
        );

        $this->cartRepository = $this->objectManager->get(
            CartRepository::class
        );

        $this->getTypoScript();
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response)
    {
        switch ($request->getMethod()) {
            case 'POST':
                $this->processPostRequest($request, $response);
                break;
            default:
                $this->logger->warning(
                    'paypal-payment-api',
                    [
                        'ERROR' => 'Method not allowed!',
                    ]
                );
                $response->withStatus(405, 'Method not allowed');
        }

        return $response;
    }

    protected function getTypoScript()
    {
        $pageId = (int)GeneralUtility::_GP('pageid');
        $GLOBALS['TSFE'] = GeneralUtility::makeInstance(
            \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::class,
            $GLOBALS['TYPO3_CONF_VARS'],
            $pageId,
            0,
            true
        );
        \TYPO3\CMS\Frontend\Utility\EidUtility::initLanguage();

        $GLOBALS['TSFE']->connectToDB();
        $GLOBALS['TSFE']->initFEuser();
        \TYPO3\CMS\Frontend\Utility\EidUtility::initTCA();

        $GLOBALS['TSFE']->initUserGroups();
        $GLOBALS['TSFE']->determineId();
        $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(
            \TYPO3\CMS\Frontend\Page\PageRepository::class
        );
        $GLOBALS['TSFE']->initTemplate();
        $GLOBALS['TSFE']->getConfigArray();

        $this->conf = $this->typoScriptService->convertTypoScriptArrayToPlainArray(
            $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_cartpaypal.']
        );
        $this->cartConf = $this->typoScriptService->convertTypoScriptArrayToPlainArray(
            $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_cart.']
        );
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function processPostRequest(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response)
    {
        $rawPostData = file_get_contents('php://input');

        $this->logger->debug(
            'paypal-payment-api',
            [
                'rawPostData' => $rawPostData,
            ]
        );

        $curlRequest = $this->getCurlRequestFromPostData($rawPostData);

        $this->execCurlRequest($curlRequest);

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

            $this->orderTransaction->setStatus($paymentStatus);
            if ($paymentStatus === 'pending') {
                $this->orderTransaction->setNote($this->curlResults['pending_reason']);
            }
        } elseif (strcmp($this->curlResult, 'invalid') == 0) {
            $this->logger->warning(
                'paypal-payment-api',
                [
                    'curlResult' => 'INVALID',
                ]
            );

            $this->orderTransaction->setStatus('invalid');
        }

        $this->orderTransactionRepository->update($this->orderTransaction);
        $this->orderPaymentRepository->update($this->orderPayment);

        $this->persistenceManager->persistAll();

        $this->updateCart();

        return [];
    }

    /**
     * @param string $rawPostData
     * @return array
     */
    protected function parseRawPostData(string $rawPostData): array
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
     * @param string $rawPostData
     * @return string
     */
    protected function getCurlRequestFromPostData(string $rawPostData): string
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

            if ($key === 'invoice') {
                $this->orderNumber = $value;
            }
        }

        return $curlRequest;
    }

    /**
     * @param string $curlRequest
     *
     * @return bool
     */
    protected function execCurlRequest(string $curlRequest): bool
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

        if (is_array($this->conf) && intval($this->conf['curl_timeout'])) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, intval($this->conf['curl_timeout']));
        } else {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Connection: Close']);

        $this->curlResult = strtolower(curl_exec($ch));
        $curlError = curl_errno($ch);

        if ($curlError != 0) {
            $this->logger->warning(
                'paypal-payment-api',
                [
                    'ERROR' => 'Can\'t connect to PayPal to validate IPN message',
                    'curl_error' => curl_error($ch),
                    'curl_request' => $curlRequest,
                    'curl_result' => $this->curlResult,
                ]
            );

            curl_close($ch);
            exit;
        }

        $this->logger->debug(
            'paypal-payment-api',
            [
                'curl_info' => curl_getinfo($ch, CURLINFO_HEADER_OUT),
                'curl_request' => $curlRequest,
                'curl_result' => $this->curlResult,
            ]
        );

        $this->curlResults = explode("\r\n\r\n", $this->curlResult);

        curl_close($ch);

        return true;
    }

    /**
     * @return string
     */
    protected function getPaypalUrl(): string
    {
        if ($this->conf['sandbox']) {
            return self::PAYPAL_API_SANDBOX;
        }

        return self::PAYPAL_API_LIVE;
    }

    protected function getOrderItem()
    {
        if ($this->orderNumber) {
            $this->orderItem = $this->orderItemRepository->findOneByOrderNumber($this->orderNumber);
        }
    }

    protected function getOrderPayment()
    {
        if ($this->orderItem) {
            $this->orderPayment = $this->orderItem->getPayment();
        }
    }

    protected function getCart()
    {
        if ($this->orderItem) {
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
    protected function addOrderTransaction(string $txn_id, string $txn_txt = '')
    {
        $this->orderTransaction = $this->objectManager->get(
            \Extcode\Cart\Domain\Model\Order\Transaction::class
        );
        $this->orderTransaction->setPid($this->orderPayment->getPid());

        $this->orderTransaction->setTxnId($txn_id);
        $this->orderTransaction->setTxnTxt($txn_txt);
        $this->orderTransactionRepository->add($this->orderTransaction);

        if ($this->orderPayment) {
            $this->orderPayment->addTransaction($this->orderTransaction);
        }

        $this->orderPaymentRepository->update($this->orderPayment);

        $this->persistenceManager->persistAll();
    }

    /**
     * @param int $status
     */
    protected function setOrderPaymentStatus(int $status)
    {
        if ($this->orderPayment) {
            $this->orderPayment->setStatus($status);
        }
    }
}
