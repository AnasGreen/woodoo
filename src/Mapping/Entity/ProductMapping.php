<?php

declare(strict_types=1);

namespace App\Mapping\Entity;

use App\Shared\Entity\Timestampable;
use App\Shared\Enum\SyncStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_mapping')]
#[ORM\UniqueConstraint(name: 'uniq_product_odoo_template', columns: ['odoo_template_id'])]
#[ORM\UniqueConstraint(name: 'uniq_product_woo', columns: ['woo_product_id'])]
#[ORM\HasLifecycleCallbacks]
class ProductMapping
{
    use Timestampable;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $odooTemplateId;

    #[ORM\Column(nullable: true)]
    private ?int $odooVariantId;

    #[ORM\Column]
    private int $wooProductId;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(enumType: SyncStatus::class)]
    private SyncStatus $syncStatus = SyncStatus::Pending;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sourceHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    public function __construct(int $odooTemplateId, int $wooProductId, ?int $odooVariantId = null)
    {
        $this->odooTemplateId = $odooTemplateId;
        $this->wooProductId = $wooProductId;
        $this->odooVariantId = $odooVariantId;
        $this->initializeTimestamps();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOdooTemplateId(): int
    {
        return $this->odooTemplateId;
    }

    public function getOdooVariantId(): ?int
    {
        return $this->odooVariantId;
    }

    public function getWooProductId(): int
    {
        return $this->wooProductId;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function getSyncStatus(): SyncStatus
    {
        return $this->syncStatus;
    }

    public function getSourceHash(): ?string
    {
        return $this->sourceHash;
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setSku(?string $sku): void
    {
        $this->sku = $sku;
    }

    public function setSourceHash(?string $sourceHash): void
    {
        $this->sourceHash = $sourceHash;
    }

    public function markSynced(\DateTimeImmutable $at): void
    {
        $this->syncStatus = SyncStatus::Succeeded;
        $this->lastSyncedAt = $at;
        $this->lastError = null;
    }

    public function markFailed(string $error): void
    {
        $this->syncStatus = SyncStatus::Failed;
        $this->lastError = $error;
    }
}
