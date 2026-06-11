<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $endDate = null;

    #[ORM\Column]
    private ?int $guestsCount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalPrice = null;

    #[ORM\Column(length: 10)]
    private ?string $currency = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'pending';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $specialRequests = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancelReason = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $guest = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Listing $listing = null;

    #[ORM\OneToOne(mappedBy: 'booking', cascade: ['persist', 'remove'])]
    private ?Payment $payment = null;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'booking')]
    private Collection $reviews;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getStartDate(): ?\DateTime { return $this->startDate; }
    public function setStartDate(\DateTime $startDate): static { $this->startDate = $startDate; return $this; }

    public function getEndDate(): ?\DateTime { return $this->endDate; }
    public function setEndDate(\DateTime $endDate): static { $this->endDate = $endDate; return $this; }

    public function getGuestsCount(): ?int { return $this->guestsCount; }
    public function setGuestsCount(int $guestsCount): static { $this->guestsCount = $guestsCount; return $this; }

    public function getTotalPrice(): ?string { return $this->totalPrice; }
    public function setTotalPrice(string $totalPrice): static { $this->totalPrice = $totalPrice; return $this; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getSpecialRequests(): ?string { return $this->specialRequests; }
    public function setSpecialRequests(?string $specialRequests): static { $this->specialRequests = $specialRequests; return $this; }

    public function getCancelReason(): ?string { return $this->cancelReason; }
    public function setCancelReason(?string $cancelReason): static { $this->cancelReason = $cancelReason; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getGuest(): ?User { return $this->guest; }
    public function setGuest(?User $guest): static { $this->guest = $guest; return $this; }

    public function getListing(): ?Listing { return $this->listing; }
    public function setListing(?Listing $listing): static { $this->listing = $listing; return $this; }

    public function getPayment(): ?Payment { return $this->payment; }
    public function setPayment(?Payment $payment): static
    {
        if ($payment === null && $this->payment !== null) {
            $this->payment->setBooking(null);
        }
        if ($payment !== null && $payment->getBooking() !== $this) {
            $payment->setBooking($this);
        }
        $this->payment = $payment;
        return $this;
    }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection { return $this->reviews; }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setBooking($this);
        }
        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review) && $review->getBooking() === $this) {
            $review->setBooking(null);
        }
        return $this;
    }
}
