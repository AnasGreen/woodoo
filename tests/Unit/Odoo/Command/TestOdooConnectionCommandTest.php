<?php

declare(strict_types=1);

namespace App\Tests\Unit\Odoo\Command;

use App\Odoo\Client\OdooClientInterface;
use App\Odoo\Command\TestOdooConnectionCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class TestOdooConnectionCommandTest extends TestCase
{
    public function testItOnlyAuthenticatesAndDoesNotExposeCredentials(): void
    {
        $client = $this->createMock(OdooClientInterface::class);
        $client->expects(self::once())->method('authenticate')->willReturn(42);
        $client->expects(self::never())->method('executeKw');
        $client->expects(self::never())->method('searchRead');
        $client->expects(self::never())->method('read');

        $tester = new CommandTester(new TestOdooConnectionCommand($client));
        $statusCode = $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $statusCode);
        self::assertStringContainsString('Connection to Odoo succeeded.', $display);
        self::assertStringNotContainsString('unit-test-api-key-not-sensitive', $display);
        self::assertStringNotContainsString('user@example.invalid', $display);
    }
}
