<?php

declare(strict_types=1);

namespace App\Odoo\Client;

interface OdooClientInterface
{
    public function authenticate(): int;

    /**
     * @param list<mixed>          $arguments
     * @param array<string, mixed> $keywordArguments
     */
    public function executeKw(
        string $model,
        string $method,
        array $arguments = [],
        array $keywordArguments = [],
    ): mixed;

    /**
     * @param list<mixed>  $domain
     * @param list<string> $fields
     *
     * @return list<array<string, mixed>>
     */
    public function searchRead(
        string $model,
        array $domain = [],
        array $fields = [],
        int $offset = 0,
        ?int $limit = null,
        ?string $order = null,
    ): array;

    /**
     * @param list<int>    $ids
     * @param list<string> $fields
     *
     * @return list<array<string, mixed>>
     */
    public function read(string $model, array $ids, array $fields = []): array;
}
