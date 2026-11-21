<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Controller;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartPaypal\Api\ApiException;
use Extcode\CartPaypal\Api\PayPalClient;
use Extcode\CartPaypal\Checkout\CheckoutException;
use Extcode\CartPaypal\Checkout\Webhook\WebhookHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Receives the webhooks of PayPal on the page type of cart_paypal. It is the
 * default action of the plugin, so its URL needs no plugin arguments.
 *
 * PayPal repeats a webhook until it is answered with a 2xx status code.
 */
final class WebhookController extends ActionController
{
    use PropagateResponseTrait;

    public function __construct(
        private readonly PayPalClient $client,
        private readonly WebhookHandler $webhookHandler,
        private readonly LoggerInterface $logger,
    ) {}

    public function webhookAction(): ResponseInterface
    {
        $this->assertPost($this->request);

        $body = (string)$this->request->getBody();

        try {
            if (!$this->client->verifyWebhookSignature($this->request, $body)) {
                $this->logger->warning('Rejected a webhook of PayPal with an invalid signature.');
                $this->respondWithJson(['message' => 'Invalid signature.'], 400);
            }

            $event = json_decode($body, true);
            $this->webhookHandler->handle(is_array($event) ? $event : []);
        } catch (ApiException $exception) {
            $this->logger->error($exception->getMessage(), [
                'issue' => $exception->getIssue(),
                'debugId' => $exception->getDebugId(),
            ]);
            // PayPal sends the webhook again
            $this->respondWithJson(['message' => 'PayPal could not be reached.'], 503);
        } catch (CheckoutException $exception) {
            // the webhook does not fit the order, a repetition does not help
            $this->logger->warning('Ignored a webhook of PayPal: ' . $exception->getMessage());
        }

        $this->respondWithJson(['status' => 'ok']);
    }
}
