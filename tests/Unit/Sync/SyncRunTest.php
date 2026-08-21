<?php

declare(strict_types=1);

namespace App\Tests\Unit\Sync;

use App\Shared\Enum\SyncStatus;
use App\Sync\Entity\SyncRun;
use App\Sync\Enum\SyncDirection;
use App\Sync\Enum\SyncType;
use App\Sync\Exception\SyncStateException;
use PHPUnit\Framework\TestCase;

final class SyncRunTest extends TestCase
{
    public function testCountersAndCompletionAreConsistent(): void
    {
        $run = new SyncRun(SyncType::Product, SyncDirection::OdooToWooCommerce);
        $run->recordCreated();
        $run->recordUpdated();
        $run->recordSkipped();
        $run->recordError();
        $run->finish(SyncStatus::Failed);

        self::assertSame(4, $run->getProcessedCount());
        self::assertSame(1, $run->getCreatedCount());
        self::assertSame(1, $run->getUpdatedCount());
        self::assertSame(1, $run->getSkippedCount());
        self::assertSame(1, $run->getErrorCount());
        self::assertNotNull($run->getFinishedAt());
    }

    public function testCannotFinishWithNonTerminalStatus(): void
    {
        $this->expectException(SyncStateException::class);
        (new SyncRun(SyncType::Order, SyncDirection::WooCommerceToOdoo))->finish(SyncStatus::Processing);
    }
}
