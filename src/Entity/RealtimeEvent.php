<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RealtimeEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RealtimeEventRepository::class)]
#[ORM\Table(name: 'realtime_events')]
#[ORM\Index(columns: ['recipient_user_id'], name: 'idx_realtime_recipient')]
#[ORM\Index(columns: ['topic'], name: 'idx_realtime_topic')]
#[ORM\Index(columns: ['created_at'], name: 'idx_realtime_created_at')]
class RealtimeEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $type;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $recipientUserId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $topic = null;

    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $type, array $payload = [], ?string $recipientUserId = null, ?string $topic = null)
    {
        $this->type = $type;
        $this->payload = $payload;
        $this->recipientUserId = $recipientUserId;
        $this->topic = $topic;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getRecipientUserId(): ?string
    {
        return $this->recipientUserId;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
