<?php

declare(strict_types=1);

namespace App\Tests\Unit\WooCommerce\Command;

use App\WooCommerce\Client\WooCommerceClientInterface;
use App\WooCommerce\Command\SearchWooCommerceProductsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SearchWooCommerceProductsCommandTest extends TestCase
{
    public function testItUsesOnlyGetAndDisplaysSelectedFields(): void
    {
        $client = $this->createMock(WooCommerceClientInterface::class);
        $client->expects(self::once())
            ->method('get')
            ->with('products', [
                'search' => 'SAMPLE',
                'per_page' => 20,
                'page' => 1,
                'orderby' => 'title',
                'order' => 'asc',
                '_fields' => 'id,name,sku,price,stock_quantity,status,categories',
            ])
            ->willReturn([
                [
                    'id' => 17,
                    'name' => 'Sample Product',
                    'sku' => 'SAMPLE-17',
                    'price' => '199.90',
                    'stock_quantity' => 8,
                    'status' => 'publish',
                    'categories' => [
                        ['id' => 3, 'name' => 'Sample Category'],
                    ],
                ],
            ]);
        $client->expects(self::never())->method('post');
        $client->expects(self::never())->method('put');

        $tester = new CommandTester(new SearchWooCommerceProductsCommand($client));
        $statusCode = $tester->execute(['term' => 'SAMPLE']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $statusCode);
        self::assertStringContainsString('Sample Product', $display);
        self::assertStringContainsString('SAMPLE-17', $display);
        self::assertStringContainsString('199.90', $display);
        self::assertStringContainsString('8', $display);
        self::assertStringContainsString('publish', $display);
        self::assertStringContainsString('Sample Category', $display);
    }

    public function testItRejectsAnEmptyTermWithoutCallingWooCommerce(): void
    {
        $client = $this->createMock(WooCommerceClientInterface::class);
        $client->expects(self::never())->method('get');
        $client->expects(self::never())->method('post');
        $client->expects(self::never())->method('put');

        $tester = new CommandTester(new SearchWooCommerceProductsCommand($client));
        $statusCode = $tester->execute(['term' => '   ']);

        self::assertSame(Command::INVALID, $statusCode);
        self::assertStringContainsString('must not be empty', $tester->getDisplay());
    }
}
