<?php

declare(strict_types=1);

namespace App\WooCommerce\Exception;

final class WooCommerceTransportException extends WooCommerceException
{
    public function __construct(string $message, private readonly ?int $statusCode = null)
    {
        parent::__construct($message);
    }

    public static function timeout(): self
    {
        return new self('The WooCommerce request timed out.');
    }

    public static function networkFailure(): self
    {
        return new self('The WooCommerce endpoint could not be reached.');
    }

    public static function forHttpStatus(int $statusCode): self
    {
        $message = match (true) {
            404 === $statusCode => 'The requested WooCommerce resource was not found.',
            429 === $statusCode => 'The WooCommerce rate limit was exceeded.',
            $statusCode >= 500 => sprintf('WooCommerce returned a server error (HTTP %d).', $statusCode),
            default => sprintf('The WooCommerce request failed with HTTP status %d.', $statusCode),
        };

        return new self($message, $statusCode);
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
