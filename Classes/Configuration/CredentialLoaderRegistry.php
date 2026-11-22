<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Configuration;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

final class CredentialLoaderRegistry
{
    /**
     * @param iterable<CredentialLoaderInterface> $credentialLoaders
     */
    public function __construct(private readonly iterable $credentialLoaders) {}

    public function getCredentials(): array
    {
        $credentials = [];

        foreach ($this->credentialLoaders as $credentialLoader) {
            $credentials = array_replace_recursive($credentialLoader->getCredentials(), $credentials);
        }

        return $credentials;
    }
}
