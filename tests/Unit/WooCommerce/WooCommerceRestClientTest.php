<?php

declare(strict_types=1);

namespace App\Tests\Unit\WooCommerce;

use App\WooCommerce\Client\WooCommerceRestClient;
use App\WooCommerce\Exception\WooCommerceAuthenticationException;
use App\WooCommerce\Exception\WooCommerceInvalidResponseException;
use App\WooCommerce\Exception\WooCommerceTransportException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WooCommerceRestClientTest extends TestCase
{
    private const CONSUMER_KEY = 'unit-test-consumer-key-not-sensitive';
    private const CONSUMER_SECRET = 'unit-test-consumer-secret-not-sensitive';

    public function testSuccessfulGetUsesVersionedUrlBasicAuthenticationAndTimeout(): void
    {
        $response = $this->jsonResponse([['id' => 17, 'name' => 'Sample Product']]);
        $client = $this->createClient([$response]);

        $result = $client->get('products');

        self::assertSame([['id' => 17, 'name' => 'Sample Product']], $result);
        self::assertSame('GET', $response->getRequestMethod());
        self::assertSame('https://shop.example.invalid/wp-json/wc/v3/products', $response->getRequestUrl());
        self::assertSame(3.0, $response->getRequestOptions()['timeout']);
        self::assertSame(3.0, $response->getRequestOptions()['max_duration']);

        $headers = $response->getRequestOptions()['headers'];
        self::assertIsArray($headers);
        self::assertStringContainsString(
            base64_encode(self::CONSUMER_KEY.':'.self::CONSUMER_SECRET),
            implode("\n", array_map(static fn (mixed $header): string => (string) $header, $headers)),
        );
    }

    public function testQueryParametersAreEncodedInRequestUrl(): void
    {
        $response = $this->jsonResponse([]);
        $client = $this->createClient([$response]);

        $client->get('products', ['search' => 'Sample product', 'per_page' => 20, 'page' => 2]);

        $query = parse_url($response->getRequestUrl(), \PHP_URL_QUERY);
        self::assertIsString($query);
        parse_str($query, $parameters);
        self::assertSame('Sample product', $parameters['search']);
        self::assertSame('20', $parameters['per_page']);
        self::assertSame('2', $parameters['page']);
    }

    public function testPostIsSupportedUsingOnlyMockHttpClient(): void
    {
        $response = $this->jsonResponse(['id' => 18, 'name' => 'Created in mock only']);
        $client = $this->createClient([$response]);

        $result = $client->post('products', ['name' => 'Created in mock only']);

        self::assertSame(18, $result['id']);
        self::assertSame('POST', $response->getRequestMethod());
        self::assertSame(['name' => 'Created in mock only'], $this->requestBody($response));
    }

    public function testPutIsSupportedUsingOnlyMockHttpClient(): void
    {
        $response = $this->jsonResponse(['id' => 18, 'name' => 'Updated in mock only']);
        $client = $this->createClient([$response]);

        $result = $client->put('products/18', ['name' => 'Updated in mock only']);

        self::assertSame('Updated in mock only', $result['name']);
        self::assertSame('PUT', $response->getRequestMethod());
        self::assertSame('https://shop.example.invalid/wp-json/wc/v3/products/18', $response->getRequestUrl());
        self::assertSame(['name' => 'Updated in mock only'], $this->requestBody($response));
    }

    /** @return iterable<string, array{int}> */
    public static function authenticationStatuses(): iterable
    {
        yield 'unauthorized' => [401];
        yield 'forbidden' => [403];
    }

    #[DataProvider('authenticationStatuses')]
    public function testAuthenticationRefusalDoesNotExposeCredentials(int $statusCode): void
    {
        $response = new MockResponse(
            'Sensitive body '.self::CONSUMER_KEY.' '.self::CONSUMER_SECRET,
            ['http_code' => $statusCode],
        );
        $client = $this->createClient([$response]);

        try {
            $client->get('products');
            self::fail('Authentication should have been refused.');
        } catch (WooCommerceAuthenticationException $exception) {
            self::assertSame('WooCommerce authentication was refused.', $exception->getMessage());
            self::assertCredentialsAreAbsent($exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function httpFailures(): iterable
    {
        yield 'not found' => [404, 'The requested WooCommerce resource was not found.'];
        yield 'rate limited' => [429, 'The WooCommerce rate limit was exceeded.'];
        yield 'server failure' => [500, 'WooCommerce returned a server error (HTTP 500).'];
    }

    #[DataProvider('httpFailures')]
    public function testHttpFailuresAreSafe(int $statusCode, string $expectedMessage): void
    {
        $response = new MockResponse(
            'Sensitive body '.self::CONSUMER_KEY.' '.self::CONSUMER_SECRET,
            ['http_code' => $statusCode],
        );
        $client = $this->createClient([$response]);

        try {
            $client->get('products/missing');
            self::fail('The HTTP failure should have been reported.');
        } catch (WooCommerceTransportException $exception) {
            self::assertSame($statusCode, $exception->getStatusCode());
            self::assertSame($expectedMessage, $exception->getMessage());
            self::assertCredentialsAreAbsent($exception->getMessage());
        }
    }

    public function testNetworkFailureIsReportedWithoutCredentials(): void
    {
        $client = $this->createClient([
            new MockResponse('', ['error' => 'Network failure '.self::CONSUMER_SECRET]),
        ]);

        try {
            $client->get('products');
            self::fail('The network failure should have been reported.');
        } catch (WooCommerceTransportException $exception) {
            self::assertSame('The WooCommerce endpoint could not be reached.', $exception->getMessage());
            self::assertCredentialsAreAbsent($exception->getMessage());
        }
    }

    public function testTimeoutIsReportedWithoutCredentials(): void
    {
        $client = $this->createClient([new MockResponse(self::timeoutBody())]);

        try {
            $client->get('products');
            self::fail('The timeout should have been reported.');
        } catch (WooCommerceTransportException $exception) {
            self::assertSame('The WooCommerce request timed out.', $exception->getMessage());
            self::assertCredentialsAreAbsent($exception->getMessage());
        }
    }

    public function testInvalidJsonIsRejectedWithoutReturningRawBody(): void
    {
        $client = $this->createClient([
            new MockResponse('Invalid JSON '.self::CONSUMER_SECRET),
        ]);

        try {
            $client->get('products');
            self::fail('Invalid JSON should have been rejected.');
        } catch (WooCommerceInvalidResponseException $exception) {
            self::assertSame('WooCommerce returned invalid JSON.', $exception->getMessage());
            self::assertCredentialsAreAbsent($exception->getMessage());
        }
    }

    public function testScalarJsonResponseIsRejected(): void
    {
        $client = $this->createClient([new MockResponse('true')]);

        $this->expectException(WooCommerceInvalidResponseException::class);
        $this->expectExceptionMessage('WooCommerce returned an invalid response payload.');
        $client->get('products');
    }

    public function testCredentialsAreRejectedFromQueryParameters(): void
    {
        $client = $this->createClient([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WooCommerce credentials must not be included in query parameters.');
        $client->get('products', ['consumer_key' => self::CONSUMER_KEY]);
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function createClient(array $responses): WooCommerceRestClient
    {
        return new WooCommerceRestClient(
            new MockHttpClient($responses),
            'https://shop.example.invalid',
            self::CONSUMER_KEY,
            self::CONSUMER_SECRET,
            3.0,
        );
    }

    private function jsonResponse(mixed $payload): MockResponse
    {
        return new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function requestBody(MockResponse $response): array
    {
        $body = $response->getRequestOptions()['body'];
        self::assertIsString($body);
        $decoded = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        self::assertStringKeyedArray($decoded);

        return $decoded;
    }

    /**
     * @phpstan-assert array<string, mixed> $value
     */
    private static function assertStringKeyedArray(mixed $value): void
    {
        self::assertIsArray($value);
    }

    /** @return \Generator<int, string, mixed, void> */
    private static function timeoutBody(): \Generator
    {
        yield '';
    }

    private static function assertCredentialsAreAbsent(string $message): void
    {
        self::assertStringNotContainsString(self::CONSUMER_KEY, $message);
        self::assertStringNotContainsString(self::CONSUMER_SECRET, $message);
    }
}
