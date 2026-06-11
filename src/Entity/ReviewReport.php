<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\ReviewReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReviewReportRepository::class)]
#[ORM\Table(name: 'review_reports')]
class ReviewReport
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'L\'avis signalé est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'reports')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Review $review = null;

    #[Assert\NotNull(message: 'L\'auteur du signalement est obligatoire.')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $reportedBy = null;

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

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(?Review $review): static
    {
        $this->review = $review;

        return $this;
    }

    public function getReportedBy(): ?User
    {
        return $this->reportedBy;
    }

    public function setReportedBy(?User $reportedBy): static
    {
        $this->reportedBy = $reportedBy;

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
}
