<?php

declare(strict_types=1);

namespace App\Sync\Entity;

use App\Shared\Enum\SyncStatus;
use App\Sync\Enum\SyncDirection;
use App\Sync\Enum\SyncType;
use App\Sync\Exception\SyncStateException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sync_run')]
class SyncRun
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: SyncType::class)]
    private SyncType $syncType;

    #[ORM\Column(enumType: SyncDirection::class)]
    private SyncDirection $direction;

    #[ORM\Column(enumType: SyncStatus::class)]
    private SyncStatus $status = SyncStatus::Pending;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column]
    private int $processedCount = 0;

    #[ORM\Column]
    private int $createdCount = 0;

    #[ORM\Column]
    private int $updatedCount = 0;

    #[ORM\Column]
    private int $skippedCount = 0;

    #[ORM\Column]
    private int $errorCount = 0;
    /** @var Collection<int, SyncError> */
    #[ORM\OneToMany(mappedBy: 'syncRun', targetEntity: SyncError::class, cascade: ['persist'])]
    private Collection $errors;

    public function __construct(SyncType $syncType, SyncDirection $direction, ?\DateTimeImmutable $startedAt = null)
    {
        $this->syncType = $syncType;
        $this->direction = $direction;
        $this->startedAt = $startedAt ?? new \DateTimeImmutable();
        $this->errors = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSyncType(): SyncType
    {
        return $this->syncType;
    }

    public function getDirection(): SyncDirection
    {
        return $this->direction;
    }

    public function getStatus(): SyncStatus
    {
        return $this->status;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    /** @return Collection<int, SyncError> */
    public function getErrors(): Collection
    {
        return $this->errors;
    }

    public function recordCreated(): void
    {
        ++$this->processedCount;
        ++$this->createdCount;
    }

    public function recordUpdated(): void
    {
        ++$this->processedCount;
        ++$this->updatedCount;
    }

    public function recordSkipped(): void
    {
        ++$this->processedCount;
        ++$this->skippedCount;
    }

    public function recordError(): void
    {
        ++$this->processedCount;
        ++$this->errorCount;
    }

    public function finish(SyncStatus $status, ?\DateTimeImmutable $at = null): void
    {
        if (!\in_array($status, [SyncStatus::Succeeded, SyncStatus::Failed], true)) {
            throw new SyncStateException('A finished sync run must be succeeded or failed.');
        }
        $this->status = $status;
        $this->finishedAt = $at ?? new \DateTimeImmutable();
    }

    public function addError(SyncError $error): void
    {
        if (!$this->errors->contains($error)) {
            $this->errors->add($error);
        }
    }
}
