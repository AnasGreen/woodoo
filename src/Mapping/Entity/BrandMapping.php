<?php

declare(strict_types=1);

namespace App\Mapping\Entity;

use App\Shared\Entity\Timestampable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'brand_mapping')]
#[ORM\UniqueConstraint(name: 'uniq_brand_odoo_value', columns: ['odoo_value_id'])]
#[ORM\UniqueConstraint(name: 'uniq_brand_woo', columns: ['woo_brand_id'])]
#[ORM\HasLifecycleCallbacks]
class BrandMapping
{
    use Timestampable;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $odooAttributeId;

    #[ORM\Column]
    private int $odooValueId;

    #[ORM\Column]
    private int $wooBrandId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $normalizedName;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    public function __construct(int $odooAttributeId, int $odooValueId, int $wooBrandId, string $name, string $normalizedName)
    {
        $this->odooAttributeId = $odooAttributeId;
        $this->odooValueId = $odooValueId;
        $this->wooBrandId = $wooBrandId;
        $this->name = $name;
        $this->normalizedName = $normalizedName;
        $this->initializeTimestamps();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOdooAttributeId(): int
    {
        return $this->odooAttributeId;
    }

    public function getOdooValueId(): int
    {
        return $this->odooValueId;
    }

    public function getWooBrandId(): int
    {
        return $this->wooBrandId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNormalizedName(): string
    {
        return $this->normalizedName;
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }
}
