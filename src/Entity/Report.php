<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\ReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Table(name: 'reports')]
class Report
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'L\'auteur du signalement est obligatoire.')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $reporter = null;

    #[Assert\NotBlank(message: 'Le type de cible est obligatoire.')]
    #[Assert\Choice(
        choices: ['user', 'property', 'review', 'message', 'reservation'],
        message: 'Le type de cible sélectionné n\'est pas valide.',
    )]
    #[ORM\Column(length: 50)]
    private ?string $targetType = null;

    #[Assert\NotNull(message: 'L\'identifiant de la cible est obligatoire.')]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $target = null;

    #[Assert\NotBlank(message: 'La raison du signalement est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 2000,
        minMessage: 'La raison doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'La raison ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $reason = null;

    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['pending', 'reviewed', 'dismissed', 'upheld'],
        message: 'Le statut sélectionné n\'est pas valide.',
    )]
    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getReporter(): ?User
    {
        return $this->reporter;
    }

    public function setReporter(?User $reporter): static
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function getTargetType(): ?string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTarget(): ?Uuid
    {
        return $this->target;
    }

    public function setTarget(Uuid $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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
