<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\ReviewRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'reviews')]
class Review
{
    use UuidEntityTrait;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $reviewer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $reviewedUser = null;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[ORM\Column]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, ReviewMedia> */
    #[ORM\OneToMany(targetEntity: ReviewMedia::class, mappedBy: 'review', orphanRemoval: true)]
    private Collection $media;

    /** @var Collection<int, ReviewReport> */
    #[ORM\OneToMany(targetEntity: ReviewReport::class, mappedBy: 'review', orphanRemoval: true)]
    private Collection $reports;

    public function __construct()
    {
        $this->media = new ArrayCollection();
        $this->reports = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getReviewer(): ?User
    {
        return $this->reviewer;
    }

    public function setReviewer(?User $reviewer): static
    {
        $this->reviewer = $reviewer;

        return $this;
    }

    public function getReviewedUser(): ?User
    {
        return $this->reviewedUser;
    }

    public function setReviewedUser(?User $reviewedUser): static
    {
        $this->reviewedUser = $reviewedUser;

        return $this;
    }

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

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

    /** @return Collection<int, ReviewMedia> */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedium(ReviewMedia $medium): static
    {
        if (!$this->media->contains($medium)) {
            $this->media->add($medium);
            $medium->setReview($this);
        }

        return $this;
    }

    public function removeMedium(ReviewMedia $medium): static
    {
        $this->media->removeElement($medium);

        return $this;
    }

    /** @return Collection<int, ReviewReport> */
    public function getReports(): Collection
    {
        return $this->reports;
    }

    public function addReport(ReviewReport $report): static
    {
        if (!$this->reports->contains($report)) {
            $this->reports->add($report);
            $report->setReview($this);
        }

        return $this;
    }

    public function removeReport(ReviewReport $report): static
    {
        $this->reports->removeElement($report);

        return $this;
    }
}
