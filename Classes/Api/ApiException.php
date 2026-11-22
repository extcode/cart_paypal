<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Api;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use RuntimeException;
use Throwable;

/**
 * A request to the PayPal REST API failed, either because PayPal could not be
 * reached or because PayPal answered with an error.
 */
final class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        int $code,
        private readonly int $statusCode = 0,
        private readonly string $name = '',
        private readonly string $issue = '',
        private readonly string $debugId = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromResponse(int $statusCode, array $data): self
    {
        $name = (string)($data['name'] ?? $data['error'] ?? '');
        $issue = (string)($data['details'][0]['issue'] ?? '');

        return new self(
            sprintf(
                'The PayPal API answered with status %d: %s',
                $statusCode,
                trim($name . ' ' . $issue) ?: 'no error name'
            ),
            1795600001,
            $statusCode,
            $name,
            $issue,
            (string)($data['debug_id'] ?? '')
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * The error name, e.g. UNPROCESSABLE_ENTITY.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * The issue of the first error detail, e.g. ORDER_ALREADY_CAPTURED.
     */
    public function getIssue(): string
    {
        return $this->issue;
    }

    /**
     * The id PayPal support needs to find the request.
     */
    public function getDebugId(): string
    {
        return $this->debugId;
    }
}
