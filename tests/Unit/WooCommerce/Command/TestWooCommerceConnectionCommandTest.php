<?php

declare(strict_types=1);

namespace App\Tests\Unit\WooCommerce\Command;

use App\WooCommerce\Client\WooCommerceClientInterface;
use App\WooCommerce\Command\TestWooCommerceConnectionCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class TestWooCommerceConnectionCommandTest extends TestCase
{
    public function testItUsesOnlyGetAndDoesNotExposeCredentials(): void
    {
        $client = $this->createMock(WooCommerceClientInterface::class);
        $client->expects(self::once())
            ->method('get')
            ->with('products', ['per_page' => 1, 'page' => 1, '_fields' => 'id'])
            ->willReturn([]);
        $client->expects(self::never())->method('post');
        $client->expects(self::never())->method('put');

        $tester = new CommandTester(new TestWooCommerceConnectionCommand($client));
        $statusCode = $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $statusCode);
        self::assertStringContainsString('Connection to WooCommerce succeeded.', $display);
        self::assertStringNotContainsString('unit-test-consumer-key-not-sensitive', $display);
        self::assertStringNotContainsString('unit-test-consumer-secret-not-sensitive', $display);
    }
}
