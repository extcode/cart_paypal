<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Configuration;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class CredentialsFromConfVars implements CredentialLoaderInterface
{
    private array $credentials;

    public function __construct(
        public readonly ExtensionConfiguration $extensionConfiguration,
    ) {
        try {
            $conf = $this->extensionConfiguration->get('cart_paypal');
        } catch (ExtensionConfigurationExtensionNotConfiguredException) {
            $this->credentials = [];
            return;
        }

        if (isset($conf['credentials']) && is_array($conf['credentials'])) {
            $this->credentials = $conf['credentials'];
        } else {
            $this->credentials = [];
        }
    }

    public static function getDefaultPriority(): int
    {
        return 30;
    }

    public function getCredentials(): array
    {
        return $this->credentials;
    }
}
