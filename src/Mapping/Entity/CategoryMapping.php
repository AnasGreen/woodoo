<?php

declare(strict_types=1);

namespace App\Mapping\Entity;

use App\Shared\Entity\Timestampable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'category_mapping')]
#[ORM\UniqueConstraint(name: 'uniq_category_odoo', columns: ['odoo_category_id'])]
#[ORM\UniqueConstraint(name: 'uniq_category_woo', columns: ['woo_category_id'])]
#[ORM\HasLifecycleCallbacks]
class CategoryMapping
{
    use Timestampable;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $odooCategoryId;

    #[ORM\Column]
    private int $wooCategoryId;

    #[ORM\Column(nullable: true)]
    private ?int $odooParentId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sourceHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    public function __construct(int $odooCategoryId, int $wooCategoryId, string $name, ?int $odooParentId = null)
    {
        $this->odooCategoryId = $odooCategoryId;
        $this->wooCategoryId = $wooCategoryId;
        $this->name = $name;
        $this->odooParentId = $odooParentId;
        $this->initializeTimestamps();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOdooCategoryId(): int
    {
        return $this->odooCategoryId;
    }

    public function getWooCategoryId(): int
    {
        return $this->wooCategoryId;
    }

    public function getOdooParentId(): ?int
    {
        return $this->odooParentId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSourceHash(): ?string
    {
        return $this->sourceHash;
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }
}
