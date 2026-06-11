<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
class AuditLog
{
    use UuidEntityTrait;

    #[ORM\ManyToOne(inversedBy: 'auditLogs')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[Assert\NotBlank(message: 'L\'action est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'L\'action ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 100)]
    private ?string $action = null;

    #[Assert\NotBlank(message: 'Le type d\'entité est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le type d\'entité ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 100)]
    private ?string $entityType = null;

    #[Assert\NotNull(message: 'L\'identifiant de l\'entité est obligatoire.')]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $entity = null;

    #[Assert\Ip(version: Assert\Ip::ALL, message: 'L\'adresse IP n\'est pas valide.')]
    #[Assert\Length(max: 45, maxMessage: 'L\'adresse IP ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntity(): ?Uuid
    {
        return $this->entity;
    }

    public function setEntity(Uuid $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
