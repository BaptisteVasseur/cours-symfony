<?php

namespace App\Entity;

use App\Enum\BookingStatus;
use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Index(name: 'idx_booking_listing_status_dates', columns: ['listing_id', 'booking_status', 'check_in', 'check_out'])]
class Booking
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Listing $listing = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $guest = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date d\'arrivée est obligatoire.')]
    private ?\DateTimeImmutable $checkIn = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de départ est obligatoire.')]
    private ?\DateTimeImmutable $checkOut = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'Le nombre de voyageurs doit être positif.')]
    private ?int $guestsCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $nightsCount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $baseAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $cleaningFee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $serviceFee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $taxesAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant total est obligatoire.')]
    #[Assert\Positive(message: 'Le montant total doit être supérieur à 0.')]
    private ?string $totalAmount = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(length: 30, enumType: BookingStatus::class)]
    private BookingStatus $bookingStatus = BookingStatus::Pending;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $cancelledBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $checkinReminderSentAt = null;

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Payment::class)]
    private ?Payment $payment = null;

    #[ORM\OneToMany(mappedBy: 'booking', targetEntity: Payout::class)]
    private Collection $payouts;

    #[ORM\OneToMany(mappedBy: 'booking', targetEntity: Review::class)]
    private Collection $reviews;

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Conversation::class)]
    private ?Conversation $conversation = null;

    #[ORM\OneToMany(mappedBy: 'booking', targetEntity: BookingHistory::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $histories;

    public function __construct()
    {
        $this->payouts = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->histories = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validateDateRange(ExecutionContextInterface $context): void
    {
        if ($this->checkIn !== null && $this->checkOut !== null && $this->checkOut <= $this->checkIn) {
            $context->buildViolation('La date de départ doit être postérieure à la date d\'arrivée.')
                ->atPath('checkOut')
                ->addViolation();
        }
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getGuest(): ?User
    {
        return $this->guest;
    }
    public function setGuest(?User $v): static
    {
        $this->guest = $v;
        return $this;
    }

    public function getCheckIn(): ?\DateTimeImmutable
    {
        return $this->checkIn;
    }
    public function setCheckIn(\DateTimeImmutable $v): static
    {
        $this->checkIn = $v;
        return $this;
    }

    public function getCheckOut(): ?\DateTimeImmutable
    {
        return $this->checkOut;
    }
    public function setCheckOut(\DateTimeImmutable $v): static
    {
        $this->checkOut = $v;
        return $this;
    }

    public function getGuestsCount(): ?int
    {
        return $this->guestsCount;
    }
    public function setGuestsCount(?int $v): static
    {
        $this->guestsCount = $v;
        return $this;
    }

    public function getNightsCount(): ?int
    {
        return $this->nightsCount;
    }
    public function setNightsCount(?int $v): static
    {
        $this->nightsCount = $v;
        return $this;
    }

    public function getBaseAmount(): ?string
    {
        return $this->baseAmount;
    }
    public function setBaseAmount(string $v): static
    {
        $this->baseAmount = $v;
        return $this;
    }

    public function getCleaningFee(): ?string
    {
        return $this->cleaningFee;
    }
    public function setCleaningFee(?string $v): static
    {
        $this->cleaningFee = $v;
        return $this;
    }

    public function getServiceFee(): ?string
    {
        return $this->serviceFee;
    }
    public function setServiceFee(?string $v): static
    {
        $this->serviceFee = $v;
        return $this;
    }

    public function getTaxesAmount(): ?string
    {
        return $this->taxesAmount;
    }
    public function setTaxesAmount(?string $v): static
    {
        $this->taxesAmount = $v;
        return $this;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }
    public function setTotalAmount(string $v): static
    {
        $this->totalAmount = $v;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }
    public function setCurrency(?string $v): static
    {
        $this->currency = $v;
        return $this;
    }

    public function getBookingStatus(): BookingStatus
    {
        return $this->bookingStatus;
    }
    public function setBookingStatus(BookingStatus $v): static
    {
        $this->bookingStatus = $v;
        return $this;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }
    public function setCancellationReason(?string $v): static
    {
        $this->cancellationReason = $v;
        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }
    public function setConfirmedAt(?\DateTimeImmutable $v): static
    {
        $this->confirmedAt = $v;
        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }
    public function setCancelledAt(?\DateTimeImmutable $v): static
    {
        $this->cancelledAt = $v;
        return $this;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }
    public function setCancelledBy(?User $v): static
    {
        $this->cancelledBy = $v;
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

    public function getCheckinReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->checkinReminderSentAt;
    }

    public function setCheckinReminderSentAt(?\DateTimeImmutable $v): static
    {
        $this->checkinReminderSentAt = $v;
        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function getPayouts(): Collection
    {
        return $this->payouts;
    }

    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function getHistories(): Collection
    {
        return $this->histories;
    }

    public function addHistory(BookingHistory $history): static
    {
        if (!$this->histories->contains($history)) {
            $this->histories->add($history);
            $history->setBooking($this);
        }
        return $this;
    }

    public function isActive(): bool
    {
        return $this->bookingStatus->blocksCalendar();
    }
}
