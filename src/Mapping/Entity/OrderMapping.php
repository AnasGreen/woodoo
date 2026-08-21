<?php

declare(strict_types=1);

namespace App\Mapping\Entity;

use App\Shared\Entity\Timestampable;
use App\Shared\Enum\SyncStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'order_mapping')]
#[ORM\UniqueConstraint(name: 'uniq_order_woo', columns: ['woo_order_id'])]
#[ORM\UniqueConstraint(name: 'uniq_order_idempotency', columns: ['idempotency_key'])]
#[ORM\HasLifecycleCallbacks]
class OrderMapping
{
    use Timestampable;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $wooOrderId;

    #[ORM\Column(nullable: true)]
    private ?int $odooSaleOrderId = null;

    #[ORM\Column(length: 191)]
    private string $idempotencyKey;

    #[ORM\Column(enumType: SyncStatus::class)]
    private SyncStatus $syncStatus = SyncStatus::Pending;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    public function __construct(int $wooOrderId, string $idempotencyKey)
    {
        $this->wooOrderId = $wooOrderId;
        $this->idempotencyKey = $idempotencyKey;
        $this->initializeTimestamps();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWooOrderId(): int
    {
        return $this->wooOrderId;
    }

    public function getOdooSaleOrderId(): ?int
    {
        return $this->odooSaleOrderId;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getSyncStatus(): SyncStatus
    {
        return $this->syncStatus;
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function attachOdooSaleOrder(int $id): void
    {
        $this->odooSaleOrderId = $id;
    }

    public function markFailed(string $error): void
    {
        $this->syncStatus = SyncStatus::Failed;
        $this->lastError = $error;
    }
}
