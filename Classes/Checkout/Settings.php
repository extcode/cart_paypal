<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Checkout;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Reads the TypoScript configuration of cart and cart_paypal.
 */
final class Settings
{
    private ?array $cartConfiguration = null;

    private ?array $configuration = null;

    public function __construct(
        private readonly ConfigurationManagerInterface $configurationManager,
    ) {}

    /**
     * The framework configuration of cart, passed to the events like cart
     * does it.
     */
    public function getCartConfiguration(): array
    {
        return $this->cartConfiguration ??= $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );
    }

    public function getCartPid(): int
    {
        return (int)($this->getCartConfiguration()['settings']['cart']['pid'] ?? 0);
    }

    public function getOrderPid(): int
    {
        return (int)($this->getCartConfiguration()['settings']['order']['pid'] ?? 0);
    }

    public function getTypeNum(): int
    {
        return (int)($this->getConfiguration()['redirectTypeNum'] ?? 0);
    }

    public function getPaymentPid(): int
    {
        return (int)($this->getSettings()['paymentPid'] ?? 0);
    }

    public function getBrandName(): string
    {
        return trim((string)($this->getSettings()['brandName'] ?? ''));
    }

    public function getLocale(): string
    {
        return trim((string)($this->getSettings()['locale'] ?? ''));
    }

    public function getShippingPreference(): string
    {
        return trim((string)($this->getSettings()['shippingPreference'] ?? '')) ?: 'NO_SHIPPING';
    }

    private function getSettings(): array
    {
        $settings = $this->getConfiguration()['settings'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    private function getConfiguration(): array
    {
        return $this->configuration ??= $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'CartPaypal'
        );
    }
}
