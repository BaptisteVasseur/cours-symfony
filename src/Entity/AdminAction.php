<?php

namespace App\Entity;

use App\Repository\AdminActionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminActionRepository::class)]
class AdminAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $actionType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $targetType = null;

    #[ORM\Column(nullable: true)]
    private ?int $targetId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'adminActions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $admin = null;

    public function getId(): ?int { return $this->id; }

    public function getActionType(): ?string { return $this->actionType; }
    public function setActionType(string $actionType): static { $this->actionType = $actionType; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getTargetType(): ?string { return $this->targetType; }
    public function setTargetType(?string $targetType): static { $this->targetType = $targetType; return $this; }

    public function getTargetId(): ?int { return $this->targetId; }
    public function setTargetId(?int $targetId): static { $this->targetId = $targetId; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getAdmin(): ?User { return $this->admin; }
    public function setAdmin(?User $admin): static { $this->admin = $admin; return $this; }
}
