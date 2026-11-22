<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Configuration;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Reads the credentials from the key `cartPaypal.credentials` of the site
 * configuration of the current request. Needed only if the sites of an
 * installation use different PayPal apps.
 */
final class CredentialsFromSiteConfiguration implements CredentialLoaderInterface
{
    public static function getDefaultPriority(): int
    {
        return 40;
    }

    public function getCredentials(): array
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return [];
        }

        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return [];
        }

        $credentials = $site->getConfiguration()['cartPaypal']['credentials'] ?? [];

        return is_array($credentials) ? $credentials : [];
    }
}
