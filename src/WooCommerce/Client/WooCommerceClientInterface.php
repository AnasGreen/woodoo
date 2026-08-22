<?php

declare(strict_types=1);

namespace App\WooCommerce\Client;

interface WooCommerceClientInterface
{
    /**
     * @param array<string, mixed> $query
     *
     * @return array<array-key, mixed>
     */
    public function get(string $resource, array $query = []): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<array-key, mixed>
     */
    public function post(string $resource, array $payload): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<array-key, mixed>
     */
    public function put(string $resource, array $payload): array;
}
