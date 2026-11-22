<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Controller;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\CartPaypal\Api\ApiException;
use Extcode\CartPaypal\Checkout\Cart\CartRecordFinder;
use Extcode\CartPaypal\Checkout\Cart\CartRestorer;
use Extcode\CartPaypal\Checkout\CheckoutException;
use Extcode\CartPaypal\Checkout\Payment\CaptureResult;
use Extcode\CartPaypal\Checkout\Payment\PaymentProcessor;
use Extcode\CartPaypal\Checkout\Payment\PayPalPayment;
use Extcode\CartPaypal\Checkout\Settings;
use Extcode\CartPaypal\Checkout\UrlBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * The endpoints of the payment of the customer on the page type of
 * cart_paypal: the requests of the JavaScript on the payment page and the
 * redirect to and from PayPal. The order is identified by the success hash
 * of the stored cart, the cancellation by its failure hash.
 */
final class ApiController extends ActionController
{
    use PropagateResponseTrait;

    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly CartRecordFinder $cartRecordFinder,
        private readonly CartRestorer $cartRestorer,
        private readonly UrlBuilder $urlBuilder,
        private readonly Settings $paypalSettings,
        private readonly FlashMessageService $flashMessageService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Called by the JavaScript when the customer clicks the PayPal button.
     */
    public function createOrderAction(): ResponseInterface
    {
        $this->assertPost($this->request);
        $cartRecord = $this->getCartRecordOrRespond();

        try {
            $order = $this->paymentProcessor->createPayPalOrder(PayPalPayment::fromCartRecord($cartRecord));
        } catch (CheckoutException) {
            $this->respondWithJson(['message' => $this->translate('payment.not_possible')], 409);
        } catch (ApiException $exception) {
            $this->logApiException($exception);
            $this->respondWithJson(['message' => $this->translate('payment.error')], 502);
        }

        $this->respondWithJson(['orderId' => $order->id]);
    }

    /**
     * Called by the JavaScript after the customer approved the payment.
     */
    public function captureOrderAction(): ResponseInterface
    {
        $this->assertPost($this->request);
        $cartRecord = $this->getCartRecordOrRespond();

        $data = json_decode((string)$this->request->getBody(), true);
        $payPalOrderId = is_array($data) && is_string($data['orderId'] ?? null) ? $data['orderId'] : '';

        try {
            $payPalPayment = PayPalPayment::fromCartRecord($cartRecord);
            $result = $this->paymentProcessor->capture($payPalPayment, $payPalOrderId);
        } catch (CheckoutException) {
            $this->respondWithJson(['message' => $this->translate('payment.not_possible')], 409);
        } catch (ApiException $exception) {
            $this->logApiException($exception);
            $this->respondWithJson(['message' => $this->translate('payment.error')], 502);
        }

        if ($result === CaptureResult::Failed) {
            $this->respondWithJson([
                'status' => $result->value,
                'message' => $this->translate('payment.declined'),
            ]);
        }

        $this->respondWithJson([
            'status' => $result->value,
            'redirectUrl' => $this->urlBuilder->getOrderUrl($payPalPayment->getOrderItem()),
        ]);
    }

    /**
     * Sends the customer to PayPal without JavaScript, e.g. if no payment page
     * is configured.
     */
    public function redirectAction(): ResponseInterface
    {
        $cartRecord = $this->getCartRecord();
        if (!$cartRecord instanceof Cart) {
            return $this->redirectToCart('access_denied');
        }

        try {
            $order = $this->paymentProcessor->createPayPalOrder(PayPalPayment::fromCartRecord($cartRecord), forRedirect: true);
        } catch (CheckoutException) {
            return $this->redirectToOrderOrCart($cartRecord);
        } catch (ApiException $exception) {
            $this->logApiException($exception);
            return $this->cancelAndRedirectToCart($cartRecord, 'payment.error');
        }

        if ($order->approveUrl === '') {
            $this->logger->error('PayPal returned no URL to approve the order.', ['payPalOrderId' => $order->id]);
            return $this->cancelAndRedirectToCart($cartRecord, 'payment.error');
        }

        return $this->redirectTo($order->approveUrl);
    }

    /**
     * The customer returns from PayPal after approving the payment. PayPal
     * appends the id of its order as "token".
     */
    public function returnAction(): ResponseInterface
    {
        $cartRecord = $this->getCartRecord();
        if (!$cartRecord instanceof Cart) {
            return $this->redirectToCart('access_denied');
        }

        $payPalOrderId = $this->request->getQueryParams()['token'] ?? '';

        try {
            $payPalPayment = PayPalPayment::fromCartRecord($cartRecord);
            $result = $this->paymentProcessor->capture($payPalPayment, is_string($payPalOrderId) ? $payPalOrderId : '');
        } catch (CheckoutException) {
            return $this->redirectToOrderOrCart($cartRecord);
        } catch (ApiException $exception) {
            $this->logApiException($exception);
            return $this->cancelAndRedirectToCart($cartRecord, 'payment.error');
        }

        if ($result === CaptureResult::Failed) {
            if ($this->paypalSettings->getPaymentPid() > 0) {
                $this->enqueueFlashMessage('extbase.flashmessages.tx_cartpaypal_payment', 'payment.declined');
                return $this->redirectTo($this->urlBuilder->getPaymentPageUrl($cartRecord->getSHash()));
            }
            return $this->cancelAndRedirectToCart($cartRecord, 'payment.declined');
        }

        return $this->redirectTo($this->urlBuilder->getOrderUrl($payPalPayment->getOrderItem()));
    }

    /**
     * The customer cancels the payment at PayPal or on the payment page and
     * returns to the cart.
     */
    public function cancelAction(): ResponseInterface
    {
        $cartRecord = $this->cartRecordFinder->findByFHash($this->getHashArgument());
        if (!$cartRecord instanceof Cart) {
            return $this->redirectToCart('access_denied');
        }

        return $this->cancelAndRedirectToCart($cartRecord, 'payment.canceled');
    }

    private function cancelAndRedirectToCart(Cart $cartRecord, string $translationKey): ResponseInterface
    {
        try {
            $canceled = $this->paymentProcessor->cancel(PayPalPayment::fromCartRecord($cartRecord));
        } catch (CheckoutException) {
            return $this->redirectToCart('access_denied');
        }

        if (!$canceled) {
            // paid in the meantime, e.g. in another tab
            return $this->redirectToOrderOrCart($cartRecord);
        }

        $this->cartRestorer->restore($cartRecord);

        return $this->redirectToCart($translationKey);
    }

    private function redirectToOrderOrCart(Cart $cartRecord): ResponseInterface
    {
        $orderItem = $cartRecord->getOrderItem();
        $payment = $orderItem?->getPayment();

        if ($orderItem !== null && $payment !== null && ($payment->isPaid() || $payment->isPending())) {
            return $this->redirectTo($this->urlBuilder->getOrderUrl($orderItem));
        }

        return $this->redirectToCart('payment.not_possible');
    }

    private function redirectToCart(string $translationKey): ResponseInterface
    {
        $this->enqueueFlashMessage('extbase.flashmessages.tx_cart_cart', $translationKey);

        return $this->redirectTo($this->urlBuilder->getCartUrl());
    }

    /**
     * Shows the message in the plugin of the page the customer is redirected to.
     */
    private function enqueueFlashMessage(string $queueIdentifier, string $translationKey): void
    {
        $this->flashMessageService
            ->getMessageQueueByIdentifier($queueIdentifier)
            ->enqueue(new FlashMessage($this->translate($translationKey), '', ContextualFeedbackSeverity::ERROR, true));
    }

    private function getCartRecord(): ?Cart
    {
        return $this->cartRecordFinder->findBySHash($this->getHashArgument());
    }

    private function getHashArgument(): string
    {
        $hash = $this->request->hasArgument('hash') ? $this->request->getArgument('hash') : '';

        return is_string($hash) ? $hash : '';
    }

    private function getCartRecordOrRespond(): Cart
    {
        $cartRecord = $this->getCartRecord();
        if (!$cartRecord instanceof Cart) {
            $this->respondWithJson(['message' => $this->translate('access_denied')], 404);
        }

        return $cartRecord;
    }

    private function logApiException(ApiException $exception): void
    {
        $this->logger->error($exception->getMessage(), [
            'issue' => $exception->getIssue(),
            'debugId' => $exception->getDebugId(),
        ]);
    }

    private function translate(string $key): string
    {
        return LocalizationUtility::translate('tx_cartpaypal.' . $key, 'CartPaypal') ?? $key;
    }
}
