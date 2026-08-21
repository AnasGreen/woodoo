<?php

declare(strict_types=1);

namespace App\Sync\Entity;

use App\Sync\Enum\WebhookStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'webhook_event')]
#[ORM\Index(name: 'idx_webhook_lookup', columns: ['provider', 'external_event_id'])]
#[ORM\UniqueConstraint(name: 'uniq_webhook_payload', columns: ['provider', 'payload_hash'])]
class WebhookEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $provider;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $externalEventId;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $entityId;

    #[ORM\Column(length: 100)]
    private string $eventType;

    #[ORM\Column(length: 64)]
    private string $payloadHash;

    #[ORM\Column(enumType: WebhookStatus::class)]
    private WebhookStatus $status = WebhookStatus::Received;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct(string $provider, string $eventType, string $payloadHash, ?string $externalEventId = null, ?string $entityId = null)
    {
        $this->provider = $provider;
        $this->eventType = $eventType;
        $this->payloadHash = $payloadHash;
        $this->externalEventId = $externalEventId;
        $this->entityId = $entityId;
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getExternalEventId(): ?string
    {
        return $this->externalEventId;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getPayloadHash(): string
    {
        return $this->payloadHash;
    }

    public function getStatus(): WebhookStatus
    {
        return $this->status;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function markProcessed(?\DateTimeImmutable $at = null): void
    {
        $this->status = WebhookStatus::Processed;
        $this->processedAt = $at ?? new \DateTimeImmutable();
    }
}
