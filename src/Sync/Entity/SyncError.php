<?php

declare(strict_types=1);

namespace App\Sync\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sync_error')]
#[ORM\Index(name: 'idx_sync_error_run', columns: ['sync_run_id'])]
#[ORM\Index(name: 'idx_sync_error_unresolved', columns: ['resolved'])]
class SyncError
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SyncRun::class, inversedBy: 'errors')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SyncRun $syncRun;

    #[ORM\Column(length: 100)]
    private string $entityType;

    #[ORM\Column(length: 191)]
    private string $externalId;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $errorCode;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column]
    private int $retryCount = 0;

    #[ORM\Column]
    private bool $resolved = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct(SyncRun $syncRun, string $entityType, string $externalId, string $message, ?string $errorCode = null)
    {
        $this->syncRun = $syncRun;
        $this->entityType = $entityType;
        $this->externalId = $externalId;
        $this->message = $message;
        $this->errorCode = $errorCode;
        $this->createdAt = new \DateTimeImmutable();
        $syncRun->addError($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSyncRun(): SyncRun
    {
        return $this->syncRun;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function incrementRetryCount(): void
    {
        ++$this->retryCount;
    }

    public function resolve(?\DateTimeImmutable $at = null): void
    {
        $this->resolved = true;
        $this->resolvedAt = $at ?? new \DateTimeImmutable();
    }
}
