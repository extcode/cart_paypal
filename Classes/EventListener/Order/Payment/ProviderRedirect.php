<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\EventListener\Order\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Event\Order\PaymentEvent;
use Extcode\CartPaypal\Checkout\Payment\PaymentProvider;
use Extcode\CartPaypal\Checkout\Settings;
use Extcode\CartPaypal\Checkout\UrlBuilder;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Stores the cart with the order and sends the customer to the payment page
 * with the PayPal button, or directly to PayPal if no payment page is
 * configured.
 *
 * The finish event of cart is not dispatched, cart_paypal dispatches its own
 * FinishEvent after the payment.
 */
final class ProviderRedirect
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly Settings $settings,
        private readonly UrlBuilder $urlBuilder,
    ) {}

    public function __invoke(PaymentEvent $event): void
    {
        $orderItem = $event->getOrderItem();

        if (!PaymentProvider::tryFrom((string)$orderItem->getPayment()?->getProvider()) instanceof PaymentProvider) {
            return;
        }

        $cartRecord = new Cart();
        $cartRecord->setOrderItem($orderItem);
        $cartRecord->setCart($event->getCart());
        $cartRecord->setPid($this->settings->getOrderPid());

        $this->cartRepository->add($cartRecord);
        $this->persistenceManager->persistAll();

        $event->setPropagationStopped(true);

        if ($this->settings->getPaymentPid() > 0) {
            $url = $this->urlBuilder->getPaymentPageUrl($cartRecord->getSHash());
        } else {
            $url = $this->urlBuilder->getApiUrl('redirect', $cartRecord->getSHash());
        }

        throw new PropagateResponseException(new RedirectResponse($url, 303), 1795600201);
    }
}
