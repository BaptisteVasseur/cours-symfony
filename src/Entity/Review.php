<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
class Review
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Listing $listing = null;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $reviewer = null;

    #[ORM\Column]
    private ?int $ratingOverall = null;

    #[ORM\Column(nullable: true)]
    private ?int $ratingCleanliness = null;

    #[ORM\Column(nullable: true)]
    private ?int $ratingCommunication = null;

    #[ORM\Column(nullable: true)]
    private ?int $ratingLocation = null;

    #[ORM\Column(nullable: true)]
    private ?int $ratingAccuracy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'review', targetEntity: ReviewPhoto::class)]
    private Collection $photos;

    public function __construct()
    {
        $this->photos = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }
    public function setBooking(?Booking $v): static
    {
        $this->booking = $v;
        return $this;
    }

    public function getListing(): ?Listing
    {
        return $this->listing;
    }
    public function setListing(?Listing $v): static
    {
        $this->listing = $v;
        return $this;
    }

    public function getReviewer(): ?User
    {
        return $this->reviewer;
    }
    public function setReviewer(?User $v): static
    {
        $this->reviewer = $v;
        return $this;
    }

    public function getRatingOverall(): ?int
    {
        return $this->ratingOverall;
    }
    public function setRatingOverall(int $v): static
    {
        $this->ratingOverall = $v;
        return $this;
    }

    public function getRatingCleanliness(): ?int
    {
        return $this->ratingCleanliness;
    }
    public function setRatingCleanliness(?int $v): static
    {
        $this->ratingCleanliness = $v;
        return $this;
    }

    public function getRatingCommunication(): ?int
    {
        return $this->ratingCommunication;
    }
    public function setRatingCommunication(?int $v): static
    {
        $this->ratingCommunication = $v;
        return $this;
    }

    public function getRatingLocation(): ?int
    {
        return $this->ratingLocation;
    }
    public function setRatingLocation(?int $v): static
    {
        $this->ratingLocation = $v;
        return $this;
    }

    public function getRatingAccuracy(): ?int
    {
        return $this->ratingAccuracy;
    }
    public function setRatingAccuracy(?int $v): static
    {
        $this->ratingAccuracy = $v;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }
    public function setComment(?string $v): static
    {
        $this->comment = $v;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function setCreatedAt(\DateTimeImmutable $v): static
    {
        $this->createdAt = $v;
        return $this;
    }

    public function getPhotos(): Collection
    {
        return $this->photos;
    }
}
