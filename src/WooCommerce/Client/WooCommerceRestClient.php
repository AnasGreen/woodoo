<?php

declare(strict_types=1);

namespace App\WooCommerce\Client;

use App\WooCommerce\Exception\WooCommerceAuthenticationException;
use App\WooCommerce\Exception\WooCommerceConfigurationException;
use App\WooCommerce\Exception\WooCommerceInvalidResponseException;
use App\WooCommerce\Exception\WooCommerceTransportException;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WooCommerceRestClient implements WooCommerceClientInterface
{
    private readonly string $apiBaseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $baseUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        private readonly float $timeout = 10.0,
    ) {
        $this->validateConfiguration($baseUrl);
        $this->apiBaseUrl = rtrim($baseUrl, '/').'/wp-json/wc/v3/';
    }

    public function get(string $resource, array $query = []): array
    {
        $this->assertNoCredentialsInQuery($query);

        return $this->request('GET', $resource, query: $query);
    }

    public function post(string $resource, array $payload): array
    {
        return $this->request('POST', $resource, payload: $payload);
    }

    public function put(string $resource, array $payload): array
    {
        return $this->request('PUT', $resource, payload: $payload);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $payload
     *
     * @return array<array-key, mixed>
     */
    private function request(string $method, string $resource, array $query = [], array $payload = []): array
    {
        $url = $this->buildUrl($resource);
        $options = [
            'auth_basic' => [$this->consumerKey, $this->consumerSecret],
            'headers' => ['Accept' => 'application/json'],
            'timeout' => $this->timeout,
            'max_duration' => $this->timeout,
        ];

        if ([] !== $query) {
            $options['query'] = $query;
        }
        if ('GET' !== $method) {
            $options['json'] = $payload;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                $this->throwHttpException($statusCode);
            }
            $content = $response->getContent(false);
        } catch (WooCommerceTransportException|WooCommerceAuthenticationException $exception) {
            throw $exception;
        } catch (TimeoutExceptionInterface) {
            throw WooCommerceTransportException::timeout();
        } catch (TransportExceptionInterface) {
            throw WooCommerceTransportException::networkFailure();
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WooCommerceInvalidResponseException('WooCommerce returned invalid JSON.');
        }

        if (!is_array($decoded)) {
            throw new WooCommerceInvalidResponseException('WooCommerce returned an invalid response payload.');
        }

        return $decoded;
    }

    private function throwHttpException(int $statusCode): never
    {
        if (401 === $statusCode || 403 === $statusCode) {
            throw new WooCommerceAuthenticationException('WooCommerce authentication was refused.');
        }

        throw WooCommerceTransportException::forHttpStatus($statusCode);
    }

    private function buildUrl(string $resource): string
    {
        $resource = trim($resource, '/');
        if ('' === $resource
            || str_contains($resource, '://')
            || 1 === preg_match('#(^|/)\.\.(/|$)#', $resource)
        ) {
            throw new \InvalidArgumentException('The WooCommerce API resource is invalid.');
        }

        return $this->apiBaseUrl.$resource;
    }

    /** @param array<string, mixed> $query */
    private function assertNoCredentialsInQuery(array $query): void
    {
        $keys = array_map('strtolower', array_keys($query));
        if (in_array('consumer_key', $keys, true) || in_array('consumer_secret', $keys, true)) {
            throw new \InvalidArgumentException('WooCommerce credentials must not be included in query parameters.');
        }
    }

    private function validateConfiguration(string $baseUrl): void
    {
        if (false === filter_var($baseUrl, \FILTER_VALIDATE_URL)
            || 'https' !== strtolower((string) parse_url($baseUrl, \PHP_URL_SCHEME))
            || null !== parse_url($baseUrl, \PHP_URL_USER)
            || null !== parse_url($baseUrl, \PHP_URL_PASS)
        ) {
            throw new WooCommerceConfigurationException('WOOCOMMERCE_URL must be a valid HTTPS URL without embedded credentials.');
        }

        if ('' === trim($this->consumerKey) || '' === $this->consumerSecret) {
            throw new WooCommerceConfigurationException('The WooCommerce credentials are incomplete.');
        }

        if ($this->timeout <= 0) {
            throw new WooCommerceConfigurationException('The WooCommerce HTTP timeout must be greater than zero.');
        }
    }
}
