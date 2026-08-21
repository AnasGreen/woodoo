<?php

declare(strict_types=1);

namespace App\Tests\Unit\Sync;

use App\Sync\Entity\WebhookEvent;
use App\Sync\Enum\WebhookStatus;
use PHPUnit\Framework\TestCase;

final class WebhookEventTest extends TestCase
{
    public function testStoresHashAndMetadataAndCanBeProcessed(): void
    {
        $event = new WebhookEvent('woocommerce', 'order.created', hash('sha256', '{}'), 'evt-1', '42');
        self::assertSame(WebhookStatus::Received, $event->getStatus());
        self::assertSame(64, strlen($event->getPayloadHash()));
        $event->markProcessed();
        self::assertSame(WebhookStatus::Processed, $event->getStatus());
        self::assertNotNull($event->getProcessedAt());
    }
}
