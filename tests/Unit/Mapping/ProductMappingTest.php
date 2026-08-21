<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mapping;

use App\Mapping\Entity\ProductMapping;
use App\Shared\Enum\SyncStatus;
use PHPUnit\Framework\TestCase;

final class ProductMappingTest extends TestCase
{
    public function testNewMappingStartsPendingAndCanSucceed(): void
    {
        $mapping = new ProductMapping(10, 20, 11);
        self::assertSame(SyncStatus::Pending, $mapping->getSyncStatus());
        self::assertSame(11, $mapping->getOdooVariantId());

        $syncedAt = new \DateTimeImmutable('2026-08-21T10:00:00+00:00');
        $mapping->markSynced($syncedAt);

        self::assertSame(SyncStatus::Succeeded, $mapping->getSyncStatus());
        self::assertSame($syncedAt, $mapping->getLastSyncedAt());
        self::assertNull($mapping->getLastError());
    }

    public function testFailureIsRecordedWithoutSecretsOrPayloads(): void
    {
        $mapping = new ProductMapping(10, 20);
        $mapping->markFailed('Remote validation failed');
        self::assertSame(SyncStatus::Failed, $mapping->getSyncStatus());
        self::assertSame('Remote validation failed', $mapping->getLastError());
    }
}
