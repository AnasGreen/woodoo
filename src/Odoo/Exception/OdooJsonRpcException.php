<?php

declare(strict_types=1);

namespace App\Odoo\Exception;

final class OdooJsonRpcException extends OdooException
{
    public function __construct(
        string $message,
        private readonly int|string|null $rpcCode = null,
    ) {
        parent::__construct($message);
    }

    public function getRpcCode(): int|string|null
    {
        return $this->rpcCode;
    }
}
