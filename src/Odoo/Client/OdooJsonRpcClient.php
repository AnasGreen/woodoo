<?php

declare(strict_types=1);

namespace App\Odoo\Client;

use App\Odoo\Exception\OdooAuthenticationException;
use App\Odoo\Exception\OdooConfigurationException;
use App\Odoo\Exception\OdooInvalidResponseException;
use App\Odoo\Exception\OdooJsonRpcException;
use App\Odoo\Exception\OdooTransportException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OdooJsonRpcClient implements OdooClientInterface
{
    private readonly string $endpoint;

    private ?int $userId = null;

    private int $requestId = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $baseUrl,
        private readonly string $database,
        private readonly string $username,
        private readonly string $apiKey,
        private readonly float $timeout = 10.0,
    ) {
        $this->validateConfiguration($baseUrl);
        $this->endpoint = rtrim($baseUrl, '/').'/jsonrpc';
    }

    public function authenticate(): int
    {
        if (null !== $this->userId) {
            return $this->userId;
        }

        $result = $this->call('common', 'authenticate', [
            $this->database,
            $this->username,
            $this->apiKey,
            new \stdClass(),
        ]);

        if (!is_int($result) || $result <= 0) {
            throw new OdooAuthenticationException('Odoo authentication was refused.');
        }

        return $this->userId = $result;
    }

    public function executeKw(
        string $model,
        string $method,
        array $arguments = [],
        array $keywordArguments = [],
    ): mixed {
        if ('' === trim($model) || '' === trim($method)) {
            throw new \InvalidArgumentException('The Odoo model and method must not be empty.');
        }

        return $this->call('object', 'execute_kw', [
            $this->database,
            $this->authenticate(),
            $this->apiKey,
            $model,
            $method,
            $arguments,
            $keywordArguments,
        ]);
    }

    public function searchRead(
        string $model,
        array $domain = [],
        array $fields = [],
        int $offset = 0,
        ?int $limit = null,
        ?string $order = null,
    ): array {
        if ($offset < 0 || (null !== $limit && $limit < 0)) {
            throw new \InvalidArgumentException('Odoo pagination values must not be negative.');
        }

        $options = ['offset' => $offset];
        if ([] !== $fields) {
            $options['fields'] = $fields;
        }
        if (null !== $limit) {
            $options['limit'] = $limit;
        }
        if (null !== $order && '' !== trim($order)) {
            $options['order'] = $order;
        }

        return $this->validateRecordList(
            $this->executeKw($model, 'search_read', [$domain], $options),
            'search_read',
        );
    }

    public function read(string $model, array $ids, array $fields = []): array
    {
        $options = [];
        if ([] !== $fields) {
            $options['fields'] = $fields;
        }

        return $this->validateRecordList(
            $this->executeKw($model, 'read', [$ids], $options),
            'read',
        );
    }

    /**
     * @param list<mixed> $arguments
     */
    private function call(string $service, string $method, array $arguments): mixed
    {
        $requestId = ++$this->requestId;
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => [
                'service' => $service,
                'method' => $method,
                'args' => $arguments,
            ],
            'id' => $requestId,
        ];

        try {
            $response = $this->httpClient->request('POST', $this->endpoint, [
                'headers' => ['Accept' => 'application/json'],
                'json' => $payload,
                'timeout' => $this->timeout,
                'max_duration' => $this->timeout,
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw OdooTransportException::unexpectedHttpStatus($statusCode);
            }
            $content = $response->getContent(false);
        } catch (OdooTransportException $exception) {
            throw $exception;
        } catch (TransportExceptionInterface) {
            throw OdooTransportException::networkFailure();
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new OdooInvalidResponseException('The Odoo endpoint returned invalid JSON.');
        }

        if (!is_array($decoded)
            || '2.0' !== ($decoded['jsonrpc'] ?? null)
            || $requestId !== ($decoded['id'] ?? null)
        ) {
            throw new OdooInvalidResponseException('The Odoo endpoint returned an invalid JSON-RPC envelope.');
        }

        if (array_key_exists('error', $decoded)) {
            $this->throwJsonRpcError($decoded['error']);
        }

        if (!array_key_exists('result', $decoded)) {
            throw new OdooInvalidResponseException('The Odoo JSON-RPC response does not contain a result.');
        }

        return $decoded['result'];
    }

    private function throwJsonRpcError(mixed $error): never
    {
        if (!is_array($error)) {
            throw new OdooInvalidResponseException('The Odoo JSON-RPC error is malformed.');
        }

        $code = $error['code'] ?? null;
        $rpcCode = is_int($code) || is_string($code) ? $code : null;
        $message = is_string($error['message'] ?? null)
            ? $this->redact($error['message'])
            : 'Unknown JSON-RPC error';

        throw new OdooJsonRpcException(sprintf('Odoo JSON-RPC error: %s', $message), $rpcCode);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function validateRecordList(mixed $result, string $operation): array
    {
        $this->assertRecordList($result, $operation);

        return $result;
    }

    /**
     * @phpstan-assert list<array<string, mixed>> $result
     */
    private function assertRecordList(mixed $result, string $operation): void
    {
        if (!is_array($result) || !array_is_list($result)) {
            throw new OdooInvalidResponseException(sprintf('Odoo %s returned an invalid record list.', $operation));
        }

        foreach ($result as $record) {
            if (!is_array($record)) {
                throw new OdooInvalidResponseException(sprintf('Odoo %s returned an invalid record.', $operation));
            }
        }
    }

    private function validateConfiguration(string $baseUrl): void
    {
        if (false === filter_var($baseUrl, \FILTER_VALIDATE_URL)
            || !in_array(parse_url($baseUrl, \PHP_URL_SCHEME), ['http', 'https'], true)
        ) {
            throw new OdooConfigurationException('ODOO_URL must be a valid HTTP or HTTPS URL.');
        }

        if ('' === trim($this->database) || '' === trim($this->username) || '' === $this->apiKey) {
            throw new OdooConfigurationException('The Odoo connection credentials are incomplete.');
        }

        if ($this->timeout <= 0) {
            throw new OdooConfigurationException('The Odoo HTTP timeout must be greater than zero.');
        }
    }

    private function redact(string $message): string
    {
        return str_replace(
            [$this->apiKey, $this->username, $this->database],
            '[redacted]',
            $message,
        );
    }
}
