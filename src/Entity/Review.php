<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $booking = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $reviewer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $reviewee = null;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Listing $listing = null;

    public function getId(): ?int { return $this->id; }

    public function getRating(): ?int { return $this->rating; }
    public function setRating(int $rating): static { $this->rating = $rating; return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $this->comment = $comment; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getBooking(): ?Booking { return $this->booking; }
    public function setBooking(?Booking $booking): static { $this->booking = $booking; return $this; }

    public function getReviewer(): ?User { return $this->reviewer; }
    public function setReviewer(?User $reviewer): static { $this->reviewer = $reviewer; return $this; }

    public function getReviewee(): ?User { return $this->reviewee; }
    public function setReviewee(?User $reviewee): static { $this->reviewee = $reviewee; return $this; }

    public function getListing(): ?Listing { return $this->listing; }
    public function setListing(?Listing $listing): static { $this->listing = $listing; return $this; }
}
