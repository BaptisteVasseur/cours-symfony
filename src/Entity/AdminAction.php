<?php

namespace App\Entity;

use App\Repository\AdminActionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AdminActionRepository::class)]
class AdminAction
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $admin = null;

    #[ORM\Column(length: 50)]
    private ?string $actionType = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $targetType = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $targetId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?Uuid { return $this->id; }

    public function getAdmin(): ?User { return $this->admin; }
    public function setAdmin(?User $v): static { $this->admin = $v; return $this; }

    public function getActionType(): ?string { return $this->actionType; }
    public function setActionType(string $v): static { $this->actionType = $v; return $this; }

    public function getTargetType(): ?string { return $this->targetType; }
    public function setTargetType(?string $v): static { $this->targetType = $v; return $this; }

    public function getTargetId(): ?Uuid { return $this->targetId; }
    public function setTargetId(?Uuid $v): static { $this->targetId = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }
}
