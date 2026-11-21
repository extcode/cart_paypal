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
use Extcode\CartPaypal\Api\PayPalClient;
use Extcode\CartPaypal\Checkout\Cart\CartRecordFinder;
use Extcode\CartPaypal\Checkout\CheckoutException;
use Extcode\CartPaypal\Checkout\Payment\OrderRequestBuilder;
use Extcode\CartPaypal\Checkout\Payment\PayPalPayment;
use Extcode\CartPaypal\Checkout\Settings;
use Extcode\CartPaypal\Checkout\UrlBuilder;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * The payment page, shows the PayPal button of the JavaScript SDK for an
 * order which is not paid yet.
 */
final class PaymentController extends ActionController
{
    use PropagateResponseTrait;

    public function __construct(
        private readonly CartRecordFinder $cartRecordFinder,
        private readonly PayPalClient $client,
        private readonly Settings $paypalSettings,
        private readonly UrlBuilder $urlBuilder,
    ) {}

    public function showAction(): ResponseInterface
    {
        $hash = $this->request->hasArgument('hash') ? $this->request->getArgument('hash') : '';
        $hash = is_string($hash) ? $hash : '';

        $cartRecord = $this->cartRecordFinder->findBySHash($hash);
        try {
            $payPalPayment = $cartRecord instanceof Cart ? PayPalPayment::fromCartRecord($cartRecord) : null;
        } catch (CheckoutException) {
            $payPalPayment = null;
        }

        if (!$payPalPayment instanceof PayPalPayment) {
            $this->view->assign('state', 'unknown');
            return $this->htmlResponse();
        }

        $orderItem = $payPalPayment->getOrderItem();
        if ($payPalPayment->isPaidOrPending()) {
            return $this->redirectTo($this->urlBuilder->getOrderUrl($orderItem));
        }
        if ($payPalPayment->isCanceled()) {
            $this->view->assignMultiple([
                'state' => 'canceled',
                'cartUrl' => $this->urlBuilder->getCartUrl(),
            ]);
            return $this->htmlResponse();
        }

        $currencyCode = strtoupper($orderItem->getCurrencyCode());

        $this->view->assignMultiple([
            // the currency format of cart is used unless cart_paypal configures its own
            'settings' => array_replace_recursive(
                ['format' => ['currency' => $this->paypalSettings->getCartConfiguration()['settings']['format']['currency'] ?? []]],
                $this->settings
            ),
            'state' => 'open',
            'paymentMethod' => $payPalPayment->isCreditCard() ? 'card' : 'paypal',
            'orderItem' => $orderItem,
            'amount' => OrderRequestBuilder::formatAmount($orderItem->getTotalGross(), $currencyCode),
            'currencyCode' => $currencyCode,
            'paypal' => [
                'sdkUrl' => $this->client->getSdkUrl(),
                'clientId' => $this->client->getClientId(),
                'locale' => $this->paypalSettings->getLocale(),
            ],
            'urls' => [
                'createOrder' => $this->urlBuilder->getApiUrl('createOrder', $hash),
                'captureOrder' => $this->urlBuilder->getApiUrl('captureOrder', $hash),
                'redirect' => $this->urlBuilder->getApiUrl('redirect', $hash),
                'cancel' => $this->urlBuilder->getApiUrl('cancel', $payPalPayment->getFHash()),
            ],
        ]);

        return $this->htmlResponse();
    }
}
