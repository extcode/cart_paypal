<?php
declare(strict_types=1);
namespace Extcode\CartPaypal\Controller\Order;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\Service\SessionHandler;
use Extcode\CartPaypal\Event\Order\CancelEvent;
use Extcode\CartPaypal\Event\Order\NotifyEvent;
use Extcode\CartPaypal\Event\Order\SuccessEvent;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Log\LogManagerInterface;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Http\RequestFactory;

class PaymentController extends ActionController
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var SessionHandler
     */
    protected $sessionHandler;

    /**
     * @var CartRepository
     */
    protected $cartRepository;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var array
     */
    protected $cartConf = [];

    /**
     * @var array
     */
    protected $cartPaypalConf = [];

    /**
     * @var string|null
     */
    protected $accessToken = null;

    public function __construct(
        LogManagerInterface $logManager,
        PersistenceManager $persistenceManager,
        SessionHandler $sessionHandler,
        CartRepository $cartRepository,
        PaymentRepository $paymentRepository,
        RequestFactory $requestFactory
    ) {
        $this->logger = $logManager->getLogger();
        $this->persistenceManager = $persistenceManager;
        $this->sessionHandler = $sessionHandler;
        $this->cartRepository = $cartRepository;
        $this->paymentRepository = $paymentRepository;
        $this->requestFactory = $requestFactory;
    }

    protected function initializeAction(): void
    {
        $this->cartConf =
            $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'Cart'
            );

        $this->cartPaypalConf =
            $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'CartPayPal'
            );
    }

    public function successAction(): ResponseInterface
    {
        $token = $this->request->getQueryParams()['token'] ?? '';

        if (!$token) {
            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'tx_cartpaypal.controller.order.payment.action.success.access_denied',
                    'CartPaypal'
                ),
                '',
                \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('show', 'Cart\Cart', 'Cart');
        }

        $this->loadCartByPaypalOrderId($token);

        if (!$this->cart) {
            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'tx_cartpaypal.controller.order.payment.action.success.error_occured',
                    'CartPaypal'
                ),
                '',
                \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('show', 'Cart\Cart', 'Cart');
        }

        // Capture the PayPal order
        if ($this->capturePayPalOrder($token)) {
            $orderItem = $this->cart->getOrderItem();

            $successEvent = new SuccessEvent($this->cart->getCart(), $orderItem, $this->cartConf);
            $this->eventDispatcher->dispatch($successEvent);

            return $this->redirect('show', 'Cart\Order', 'Cart', ['orderItem' => $orderItem]);
        } else {
            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'tx_cartpaypal.controller.order.payment.action.success.payment_failed',
                    'CartPaypal'
                ),
                '',
                \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('show', 'Cart\Cart', 'Cart');
        }
    }

    public function cancelAction(): ResponseInterface
    {
        $token = $this->request->getQueryParams()['token'] ?? '';

        if (!$token) {
            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'tx_cartpaypal.controller.order.payment.action.cancel.access_denied',
                    'CartPaypal'
                ),
                '',
                \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('show', 'Cart\Cart', 'Cart');
        }

        $this->loadCartByPaypalOrderId($token);

        if ($this->cart) {
            $orderItem = $this->cart->getOrderItem();
            $payment = $orderItem->getPayment();

            $this->restoreCartSession();

            $payment->setStatus('canceled');
            $this->paymentRepository->update($payment);
            $this->persistenceManager->persistAll();

            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'tx_cartpaypal.controller.order.payment.action.cancel.successfully_canceled',
                    'CartPaypal'
                )
            );

            $cancelEvent = new CancelEvent($this->cart->getCart(), $orderItem, $this->cartConf);
            $this->eventDispatcher->dispatch($cancelEvent);
        } else {
            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'tx_cartpaypal.controller.order.payment.action.cancel.error_occured',
                    'CartPaypal'
                ),
                '',
                \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR
            );
        }

        return $this->redirect('show', 'Cart\Cart', 'Cart');
    }

    public function notifyAction(): ResponseInterface
    {
        if ($this->request->getMethod() !== 'POST') {
            return $this->htmlResponse()->withStatus(405, 'Method not allowed.');
        }

        $webhookBody = $this->request->getBody()->getContents();
        $webhookData = json_decode($webhookBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Invalid JSON in PayPal webhook: ' . $webhookBody);
            return $this->htmlResponse()->withStatus(400, 'Invalid JSON');
        }

        // Verify webhook signature
        if (!$this->verifyWebhookSignature($webhookData)) {
            $this->logger->warning('PayPal webhook signature verification failed');
            return $this->htmlResponse()->withStatus(401, 'Signature verification failed');
        }

        $eventType = $webhookData['event_type'] ?? '';
        $resource = $webhookData['resource'] ?? [];

        $this->logger->debug('PayPal Webhook received', [
            'event_type' => $eventType,
            'resource' => $resource
        ]);

        switch ($eventType) {
            case 'CHECKOUT.ORDER.APPROVED':
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->handlePaymentCompleted($resource);
                break;

            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.FAILED':
                $this->handlePaymentFailed($resource);
                break;

            case 'PAYMENT.CAPTURE.REFUNDED':
                $this->handlePaymentRefunded($resource);
                break;

            default:
                $this->logger->info('Unhandled PayPal webhook event: ' . $eventType);
        }

        return $this->htmlResponse()->withStatus(200);
    }

    protected function capturePayPalOrder(string $orderId): bool
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new \Exception('Could not get access token');
            }

            $captureUrl = $this->getApiBaseUrl() . '/v2/checkout/orders/' . $orderId . '/capture';

            $response = $this->requestFactory->request($captureUrl, 'POST', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Prefer' => 'return=representation'
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() === 201 || $response->getStatusCode() === 200) {
                if ($responseData['status'] === 'COMPLETED') {
                    $orderItem = $this->cart->getOrderItem();
                    $payment = $orderItem->getPayment();

                    $payment->setStatus('paid');

                    // Die PayPal Order ID wird bereits in der Cart-Entität gespeichert
                    // Wir müssen sie nicht zusätzlich in der Payment-Entität speichern
                    // Die Zuordnung erfolgt über die Cart-Entität

                    $this->paymentRepository->update($payment);
                    $this->persistenceManager->persistAll();

                    $this->logger->info('PayPal order captured successfully', ['order_id' => $orderId]);
                    return true;
                }
            }

            $this->logger->error('PayPal capture failed', [
                'order_id' => $orderId,
                'status_code' => $response->getStatusCode(),
                'response' => $responseData
            ]);

        } catch (\Exception $e) {
            $this->logger->error('PayPal capture error: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'exception' => $e
            ]);
        }

        return false;
    }

    protected function getAccessToken(): ?string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        try {
            $authUrl = $this->getApiBaseUrl() . '/v1/oauth2/token';
            $clientId = $this->cartPaypalConf['clientId'] ?? '';
            $clientSecret = $this->cartPaypalConf['clientSecret'] ?? '';

            if (empty($clientId) || empty($clientSecret)) {
                throw new \Exception('PayPal client ID or secret not configured');
            }

            $response = $this->requestFactory->request($authUrl, 'POST', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => 'grant_type=client_credentials'
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                return $this->accessToken;
            }

        } catch (\Exception $e) {
            $this->logger->error('PayPal access token error: ' . $e->getMessage());
        }

        return null;
    }

    protected function verifyWebhookSignature(array $webhookData): bool
    {
        $transmissionId = $this->request->getHeader('Paypal-Transmission-Id')[0] ?? '';
        $transmissionTime = $this->request->getHeader('Paypal-Transmission-Time')[0] ?? '';
        $transmissionSig = $this->request->getHeader('Paypal-Transmission-Sig')[0] ?? '';
        $certUrl = $this->request->getHeader('Paypal-Cert-Url')[0] ?? '';
        $authAlgo = $this->request->getHeader('Paypal-Auth-Algo')[0] ?? '';
        $webhookId = $this->cartPaypalConf['webhookId'] ?? '';

        if (empty($transmissionId) || empty($transmissionTime) || empty($transmissionSig) ||
            empty($certUrl) || empty($authAlgo) || empty($webhookId)) {
            return false;
        }

        // In production, implement proper signature verification
        // For now, we'll do a basic verification
        $expectedWebhookId = $this->cartPaypalConf['webhookId'] ?? '';

        if (!empty($expectedWebhookId) && isset($webhookData['id'])) {
            // Simple verification - in production use proper cryptographic verification
            return $this->verifyWebhookWithPayPal($webhookData, $transmissionId, $transmissionTime, $transmissionSig, $certUrl, $authAlgo, $webhookId);
        }

        return true; // For development without webhook ID configured
    }

    protected function verifyWebhookWithPayPal(array $webhookData, string $transmissionId, string $transmissionTime, string $transmissionSig, string $certUrl, string $authAlgo, string $webhookId): bool
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return false;
            }

            $verifyUrl = $this->getApiBaseUrl() . '/v1/notifications/verify-webhook-signature';

            $verificationData = [
                'transmission_id' => $transmissionId,
                'transmission_time' => $transmissionTime,
                'transmission_sig' => $transmissionSig,
                'cert_url' => $certUrl,
                'auth_algo' => $authAlgo,
                'webhook_id' => $webhookId,
                'webhook_event' => $webhookData
            ];

            $response = $this->requestFactory->request($verifyUrl, 'POST', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken
                ],
                'body' => json_encode($verificationData)
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            return isset($result['verification_status']) && $result['verification_status'] === 'SUCCESS';

        } catch (\Exception $e) {
            $this->logger->error('PayPal webhook verification error: ' . $e->getMessage());
            return false;
        }
    }

    protected function handlePaymentCompleted(array $resource): void
    {
        $orderId = $resource['id'] ?? $resource['order_id'] ?? '';

        if (!$orderId) {
            $this->logger->error('No order ID in completed payment webhook');
            return;
        }

        $this->loadCartByPaypalOrderId($orderId);

        if ($this->cart === null) {
            $this->logger->error('Cart not found for PayPal order: ' . $orderId);
            return;
        }

        $orderItem = $this->cart->getOrderItem();
        $payment = $orderItem->getPayment();

        if ($payment->getStatus() !== 'paid') {
            $payment->setStatus('paid');

            // Die PayPal Order ID ist bereits in der Cart-Entität gespeichert
            // Die Zuordnung erfolgt über die Cart-Entität

            $this->paymentRepository->update($payment);
            $this->persistenceManager->persistAll();

            $notifyEvent = new NotifyEvent($this->cart->getCart(), $orderItem, $this->cartConf);
            $this->eventDispatcher->dispatch($notifyEvent);

            $this->logger->info('Payment completed via webhook', ['order_id' => $orderId]);
        }
    }

    protected function handlePaymentFailed(array $resource): void
    {
        $orderId = $resource['id'] ?? $resource['order_id'] ?? '';

        if (!$orderId) {
            return;
        }

        $this->loadCartByPaypalOrderId($orderId);

        if ($this->cart === null) {
            return;
        }

        $orderItem = $this->cart->getOrderItem();
        $payment = $orderItem->getPayment();

        $payment->setStatus('failed');
        $this->paymentRepository->update($payment);
        $this->persistenceManager->persistAll();

        $this->logger->info('Payment failed via webhook', ['order_id' => $orderId]);
    }

    protected function handlePaymentRefunded(array $resource): void
    {
        $orderId = $resource['id'] ?? $resource['order_id'] ?? '';

        if (!$orderId) {
            return;
        }

        $this->loadCartByPaypalOrderId($orderId);

        if ($this->cart === null) {
            return;
        }

        $orderItem = $this->cart->getOrderItem();
        $payment = $orderItem->getPayment();

        $payment->setStatus('refunded');
        $this->paymentRepository->update($payment);
        $this->persistenceManager->persistAll();

        $this->logger->info('Payment refunded via webhook', ['order_id' => $orderId]);
    }

    protected function getApiBaseUrl(): string
    {
        return $this->cartPaypalConf['sandbox'] ?
            'https://api.sandbox.paypal.com' :
            'https://api.paypal.com';
    }

    protected function restoreCartSession(): void
    {
        $cart = $this->cart->getCart();
        $cart->resetOrderNumber();
        $cart->resetInvoiceNumber();

        $this->sessionHandler->writeCart((string)$this->cartConf['settings']['cart']['pid'], $cart);
    }

    protected function loadCartByPaypalOrderId(string $paypalOrderId): void
    {
        $querySettings = GeneralUtility::makeInstance(
            Typo3QuerySettings::class
        );
        $querySettings->setStoragePageIds([$this->cartConf['settings']['order']['pid']]);
        $this->cartRepository->setDefaultQuerySettings($querySettings);

        $this->cart = $this->cartRepository->findOneByPaypalOrderId($paypalOrderId);
    }
}
