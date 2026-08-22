<?php

declare(strict_types=1);

namespace App\Tests\Unit\Odoo\Command;

use App\Odoo\Client\OdooClientInterface;
use App\Odoo\Command\SearchOdooProductsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SearchOdooProductsCommandTest extends TestCase
{
    public function testItUsesOnlySearchReadAndDisplaysSelectedFields(): void
    {
        $client = $this->createMock(OdooClientInterface::class);
        $client->expects(self::never())->method('authenticate');
        $client->expects(self::never())->method('executeKw');
        $client->expects(self::never())->method('read');
        $client->expects(self::once())
            ->method('searchRead')
            ->with(
                'product.template',
                [
                    '|',
                    ['name', 'ilike', 'SAMPLE'],
                    ['default_code', 'ilike', 'SAMPLE'],
                ],
                ['id', 'name', 'default_code', 'list_price', 'public_categ_ids'],
                0,
                20,
                'name asc',
            )
            ->willReturn([
                [
                    'id' => 17,
                    'name' => 'Sample Product',
                    'default_code' => 'SAMPLE-17',
                    'list_price' => 199.9,
                    'public_categ_ids' => [3, 8],
                ],
            ]);

        $tester = new CommandTester(new SearchOdooProductsCommand($client));
        $statusCode = $tester->execute(['term' => 'SAMPLE']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $statusCode);
        self::assertStringContainsString('Sample Product', $display);
        self::assertStringContainsString('SAMPLE-17', $display);
        self::assertStringContainsString('199.9', $display);
        self::assertStringContainsString('3, 8', $display);
    }

    public function testItRejectsAnEmptyTermWithoutCallingOdoo(): void
    {
        $client = $this->createMock(OdooClientInterface::class);
        $client->expects(self::never())->method('authenticate');
        $client->expects(self::never())->method('executeKw');
        $client->expects(self::never())->method('searchRead');
        $client->expects(self::never())->method('read');

        $tester = new CommandTester(new SearchOdooProductsCommand($client));
        $statusCode = $tester->execute(['term' => '   ']);

        self::assertSame(Command::INVALID, $statusCode);
        self::assertStringContainsString('must not be empty', $tester->getDisplay());
    }
}
