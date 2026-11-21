<?php

declare(strict_types=1);

namespace Extcode\PaypalTest\Controller;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart\Cart;
use Extcode\Cart\Domain\Model\Cart\Service;
use Extcode\Cart\Domain\Model\Order\Item;
use Extcode\Cart\Domain\Repository\Order\ItemRepository;
use Extcode\Cart\Event\Order\PaymentEvent;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Dispatches the PaymentEvent for the order 1 like the OrderController of
 * cart does it after the order is stored.
 */
final class PaymentEventController extends ActionController
{
    public function __construct(
        private readonly ItemRepository $itemRepository,
    ) {}

    public function dispatchAction(): ResponseInterface
    {
        $query = $this->itemRepository->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $orderItem = $query->matching($query->equals('uid', 1))->execute()->getFirst();
        if (!$orderItem instanceof Item) {
            return $this->htmlResponse('no order');
        }

        $cart = new Cart([]);
        $cart->setBillingCountry('de');
        $cart->setOrderNumber((string)$orderItem->getOrderNumber());
        $cart->setPayment(new Service(1, []));

        $settings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );

        $event = new PaymentEvent($cart, $orderItem, $settings);
        $this->eventDispatcher->dispatch($event);

        return $this->htmlResponse($event->isPropagationStopped() ? 'stopped' : 'not stopped');
    }
}
