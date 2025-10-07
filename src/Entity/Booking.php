<?php

namespace App\Entity;

use App\Enum\BookingStatusEnum;
use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $checkIn = null;

    #[ORM\Column]
    private ?\DateTime $checkOut = null;

    #[ORM\Column]
    private ?float $totalPrice = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt;

    #[ORM\Column(enumType: BookingStatusEnum::class)]
    private ?BookingStatusEnum $status;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $guest = null;

    #[ORM\OneToOne(mappedBy: 'Booking', cascade: ['persist', 'remove'])]
    private ?Review $review = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = BookingStatusEnum::PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCheckIn(): ?\DateTime
    {
        return $this->checkIn;
    }

    public function setCheckIn(\DateTime $checkIn): static
    {
        $this->checkIn = $checkIn;

        return $this;
    }

    public function getCheckOut(): ?\DateTime
    {
        return $this->checkOut;
    }

    public function setCheckOut(\DateTime $checkOut): static
    {
        $this->checkOut = $checkOut;

        return $this;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

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

    public function getStatus(): ?BookingStatusEnum
    {
        return $this->status;
    }

    public function setStatus(BookingStatusEnum $status): static
    {
        $this->status = $status;

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

    public function getGuest(): ?User
    {
        return $this->guest;
    }

    public function setGuest(?User $guest): static
    {
        $this->guest = $guest;

        return $this;
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(Review $review): static
    {
        // set the owning side of the relation if necessary
        if ($review->getBooking() !== $this) {
            $review->setBooking($this);
        }

        $this->review = $review;

        return $this;
    }
}
