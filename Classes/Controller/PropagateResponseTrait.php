<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Controller;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;

/**
 * The responses of the plugins are propagated with a
 * PropagateResponseException: the response of an Extbase plugin inside a page
 * keeps neither status code nor headers, and its redirects are not passed on
 * in all TYPO3 versions.
 */
trait PropagateResponseTrait
{
    private function assertPost(ServerRequestInterface $request): void
    {
        if ($request->getMethod() !== 'POST') {
            throw new PropagateResponseException(
                new JsonResponse(['message' => 'Method not allowed.'], 405, ['Allow' => 'POST']),
                1795600301
            );
        }
    }

    private function respondWithJson(array $data, int $statusCode = 200): never
    {
        throw new PropagateResponseException(new JsonResponse($data, $statusCode), 1795600302);
    }

    private function redirectTo(string $uri): never
    {
        throw new PropagateResponseException(new RedirectResponse($uri, 303), 1795600303);
    }
}
