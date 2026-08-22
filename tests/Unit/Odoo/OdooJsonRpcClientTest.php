<?php

declare(strict_types=1);

namespace App\Tests\Unit\Odoo;

use App\Odoo\Client\OdooJsonRpcClient;
use App\Odoo\Exception\OdooAuthenticationException;
use App\Odoo\Exception\OdooInvalidResponseException;
use App\Odoo\Exception\OdooJsonRpcException;
use App\Odoo\Exception\OdooTransportException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OdooJsonRpcClientTest extends TestCase
{
    private const API_KEY = 'unit-test-api-key-not-sensitive';

    public function testAuthenticationSucceeds(): void
    {
        $response = $this->jsonResponse(42, 1);
        $client = $this->createClient([$response]);

        self::assertSame(42, $client->authenticate());
        self::assertSame('POST', $response->getRequestMethod());
        self::assertSame('https://odoo.example.invalid/jsonrpc', $response->getRequestUrl());
        self::assertSame(3.0, $response->getRequestOptions()['timeout']);
        self::assertSame(3.0, $response->getRequestOptions()['max_duration']);

        $payload = $this->requestPayload($response);
        self::assertSame('common', $payload['params']['service']);
        self::assertSame('authenticate', $payload['params']['method']);
        self::assertSame(['test_database', 'user@example.invalid', self::API_KEY, []], $payload['params']['args']);
    }

    public function testAuthenticationRefusalDoesNotExposeSecret(): void
    {
        $client = $this->createClient([$this->jsonResponse(false, 1)]);

        try {
            $client->authenticate();
            self::fail('Authentication should have been refused.');
        } catch (OdooAuthenticationException $exception) {
            self::assertStringNotContainsString(self::API_KEY, $exception->getMessage());
            self::assertSame('Odoo authentication was refused.', $exception->getMessage());
        }
    }

    public function testJsonRpcErrorIsReportedAndSecretIsRedacted(): void
    {
        $response = new MockResponse(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => 100,
                'message' => 'Rejected credential '.self::API_KEY,
            ],
        ], \JSON_THROW_ON_ERROR));
        $client = $this->createClient([$response]);

        try {
            $client->authenticate();
            self::fail('The JSON-RPC error should have been reported.');
        } catch (OdooJsonRpcException $exception) {
            self::assertSame(100, $exception->getRpcCode());
            self::assertStringContainsString('[redacted]', $exception->getMessage());
            self::assertStringNotContainsString(self::API_KEY, $exception->getMessage());
        }
    }

    public function testUnexpectedHttpStatusDoesNotExposeResponseBody(): void
    {
        $response = new MockResponse('Response contains '.self::API_KEY, ['http_code' => 503]);
        $client = $this->createClient([$response]);

        try {
            $client->authenticate();
            self::fail('The HTTP failure should have been reported.');
        } catch (OdooTransportException $exception) {
            self::assertSame('The Odoo endpoint returned HTTP status 503.', $exception->getMessage());
            self::assertStringNotContainsString(self::API_KEY, $exception->getMessage());
        }
    }

    public function testNetworkFailureIsReported(): void
    {
        $client = $this->createClient([
            new MockResponse('', ['error' => 'Simulated network failure']),
        ]);

        $this->expectException(OdooTransportException::class);
        $this->expectExceptionMessage('The Odoo endpoint could not be reached.');
        $client->authenticate();
    }

    public function testInvalidJsonResponseIsRejected(): void
    {
        $client = $this->createClient([new MockResponse('not-json')]);

        $this->expectException(OdooInvalidResponseException::class);
        $this->expectExceptionMessage('The Odoo endpoint returned invalid JSON.');
        $client->authenticate();
    }

    public function testExecuteKwAuthenticatesAndCallsObjectService(): void
    {
        $authentication = $this->jsonResponse(42, 1);
        $execution = $this->jsonResponse(['count' => 2], 2);
        $client = $this->createClient([$authentication, $execution]);

        $result = $client->executeKw('res.partner', 'search_count', [[['active', '=', true]]]);

        self::assertSame(['count' => 2], $result);
        $payload = $this->requestPayload($execution);
        self::assertSame('object', $payload['params']['service']);
        self::assertSame('execute_kw', $payload['params']['method']);
        self::assertSame(
            ['test_database', 42, self::API_KEY, 'res.partner', 'search_count', [[['active', '=', true]]], []],
            $payload['params']['args'],
        );
    }

    public function testSearchReadBuildsReadOnlyArguments(): void
    {
        $execution = $this->jsonResponse([
            ['id' => 7, 'name' => 'Example'],
        ], 2);
        $client = $this->createClient([$this->jsonResponse(42, 1), $execution]);

        $records = $client->searchRead(
            'res.partner',
            [['active', '=', true]],
            ['id', 'name'],
            offset: 5,
            limit: 10,
            order: 'name asc',
        );

        self::assertSame([['id' => 7, 'name' => 'Example']], $records);
        $arguments = $this->executeKwArguments($execution);
        self::assertSame('res.partner', $arguments[3]);
        self::assertSame('search_read', $arguments[4]);
        self::assertSame([[['active', '=', true]]], $arguments[5]);
        self::assertSame(
            ['offset' => 5, 'fields' => ['id', 'name'], 'limit' => 10, 'order' => 'name asc'],
            $arguments[6],
        );
    }

    public function testReadBuildsReadOnlyArguments(): void
    {
        $execution = $this->jsonResponse([
            ['id' => 7, 'name' => 'Example'],
            ['id' => 8, 'name' => 'Second'],
        ], 2);
        $client = $this->createClient([$this->jsonResponse(42, 1), $execution]);

        $records = $client->read('res.partner', [7, 8], ['id', 'name']);

        self::assertCount(2, $records);
        $arguments = $this->executeKwArguments($execution);
        self::assertSame('res.partner', $arguments[3]);
        self::assertSame('read', $arguments[4]);
        self::assertSame([[7, 8]], $arguments[5]);
        self::assertSame(['fields' => ['id', 'name']], $arguments[6]);
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function createClient(array $responses): OdooJsonRpcClient
    {
        return new OdooJsonRpcClient(
            new MockHttpClient($responses),
            'https://odoo.example.invalid',
            'test_database',
            'user@example.invalid',
            self::API_KEY,
            3.0,
        );
    }

    private function jsonResponse(mixed $result, int $id): MockResponse
    {
        return new MockResponse(json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{
     *     jsonrpc: string,
     *     method: string,
     *     params: array{service: string, method: string, args: list<mixed>},
     *     id: int
     * }
     */
    private function requestPayload(MockResponse $response): array
    {
        $options = $response->getRequestOptions();
        self::assertArrayHasKey('body', $options);
        self::assertIsString($options['body']);
        $payload = json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR);
        $this->assertRequestPayload($payload);

        return $payload;
    }

    /**
     * @phpstan-assert array{
     *     jsonrpc: string,
     *     method: string,
     *     params: array{service: string, method: string, args: list<mixed>},
     *     id: int
     * } $payload
     */
    private function assertRequestPayload(mixed $payload): void
    {
        self::assertIsArray($payload);
        self::assertIsString($payload['jsonrpc'] ?? null);
        self::assertIsString($payload['method'] ?? null);
        self::assertIsInt($payload['id'] ?? null);

        $params = $payload['params'] ?? null;
        self::assertIsArray($params);
        self::assertIsString($params['service'] ?? null);
        self::assertIsString($params['method'] ?? null);
        self::assertIsArray($params['args'] ?? null);
        self::assertTrue(array_is_list($params['args']));
    }

    /**
     * @return array{string, int, string, string, string, list<mixed>, array<string, mixed>}
     */
    private function executeKwArguments(MockResponse $response): array
    {
        $payload = $this->requestPayload($response);
        $arguments = $payload['params']['args'];
        $this->assertExecuteKwArguments($arguments);

        return $arguments;
    }

    /**
     * @phpstan-assert array{string, int, string, string, string, list<mixed>, array<string, mixed>} $arguments
     */
    private function assertExecuteKwArguments(mixed $arguments): void
    {
        self::assertIsArray($arguments);
        self::assertCount(7, $arguments);
        self::assertIsString($arguments[0]);
        self::assertIsInt($arguments[1]);
        self::assertIsString($arguments[2]);
        self::assertIsString($arguments[3]);
        self::assertIsString($arguments[4]);
        self::assertIsArray($arguments[5]);
        self::assertTrue(array_is_list($arguments[5]));
        self::assertIsArray($arguments[6]);
    }
}
