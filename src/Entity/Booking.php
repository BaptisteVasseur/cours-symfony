<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $guest = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $checkInDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $checkOutDate = null;

    #[ORM\Column]
    private ?int $numberOfGuests = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $commissionAmount = null;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private ?string $status = 'pending';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(mappedBy: 'booking', cascade: ['persist', 'remove'])]
    private ?Payment $payment = null;

    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'booking')]
    private Collection $reviews;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'booking')]
    private Collection $messages;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCheckInDate(): ?\DateTimeImmutable
    {
        return $this->checkInDate;
    }

    public function setCheckInDate(\DateTimeImmutable $checkInDate): static
    {
        $this->checkInDate = $checkInDate;
        return $this;
    }

    public function getCheckOutDate(): ?\DateTimeImmutable
    {
        return $this->checkOutDate;
    }

    public function setCheckOutDate(\DateTimeImmutable $checkOutDate): static
    {
        $this->checkOutDate = $checkOutDate;
        return $this;
    }

    public function getNumberOfGuests(): ?int
    {
        return $this->numberOfGuests;
    }

    public function setNumberOfGuests(int $numberOfGuests): static
    {
        $this->numberOfGuests = $numberOfGuests;
        return $this;
    }

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function getCommissionAmount(): ?string
    {
        return $this->commissionAmount;
    }

    public function setCommissionAmount(?string $commissionAmount): static
    {
        $this->commissionAmount = $commissionAmount;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): static
    {
        if ($payment !== null && $payment->getBooking() !== $this) {
            $payment->setBooking($this);
        }
        $this->payment = $payment;
        return $this;
    }

    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function getNights(): int
    {
        if ($this->checkInDate === null || $this->checkOutDate === null) {
            return 0;
        }
        return $this->checkInDate->diff($this->checkOutDate)->days;
    }
}
