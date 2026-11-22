<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Order\Item;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Builds the absolute URLs of the payment process.
 */
final class UrlBuilder
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly Settings $settings,
    ) {}

    /**
     * An endpoint of the Api plugin on the page type of cart_paypal.
     */
    public function getApiUrl(string $action, string $hash): string
    {
        return $this->build($this->settings->getCartPid(), [
            'tx_cartpaypal_api' => [
                'controller' => 'Api',
                'action' => $action,
                'hash' => $hash,
            ],
            'type' => $this->settings->getTypeNum(),
        ]);
    }

    public function getPaymentPageUrl(string $hash): string
    {
        return $this->build($this->settings->getPaymentPid(), [
            'tx_cartpaypal_payment' => [
                'hash' => $hash,
            ],
        ]);
    }

    /**
     * The confirmation page of the order, like after an order without
     * payment provider.
     */
    public function getOrderUrl(Item $orderItem): string
    {
        return $this->build($this->settings->getCartPid(), [
            'tx_cart_cart' => [
                'controller' => 'Cart\\Order',
                'action' => 'show',
                'orderItem' => $orderItem->getUid(),
            ],
        ]);
    }

    public function getCartUrl(): string
    {
        return $this->build($this->settings->getCartPid(), []);
    }

    private function build(int $pageId, array $parameters): string
    {
        $language = $this->getCurrentLanguage();
        if ($language instanceof SiteLanguage) {
            $parameters['_language'] = $language;
        }

        return (string)$this->siteFinder->getSiteByPageId($pageId)->getRouter()->generateUri($pageId, $parameters);
    }

    private function getCurrentLanguage(): ?SiteLanguage
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return null;
        }

        $language = $request->getAttribute('language');

        return $language instanceof SiteLanguage ? $language : null;
    }
}
