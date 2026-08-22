<?php

declare(strict_types=1);

namespace App\Odoo\Exception;

final class OdooTransportException extends OdooException
{
    public static function networkFailure(): self
    {
        return new self('The Odoo endpoint could not be reached.');
    }

    public static function unexpectedHttpStatus(int $statusCode): self
    {
        return new self(sprintf('The Odoo endpoint returned HTTP status %d.', $statusCode));
    }
}
