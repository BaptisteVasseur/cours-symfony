<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservations')]
class Reservation
{
    use UuidEntityTrait;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le logement est obligatoire.')]
    private ?Property $property = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le voyageur est obligatoire.')]
    private ?User $guest = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date d\'arrivée est obligatoire.')]
    private ?\DateTimeImmutable $checkinDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de départ est obligatoire.')]
    #[Assert\GreaterThan(propertyPath: 'checkinDate', message: 'La date de départ doit être après la date d\'arrivée.')]
    private ?\DateTimeImmutable $checkoutDate = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Positive(message: 'Le nombre de voyageurs doit être positif.')]
    private ?int $guestsCount = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['pending', 'confirmed', 'cancelled', 'completed'], message: 'Statut invalide.')]
    private ?string $status = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix total est obligatoire.')]
    #[Assert\Positive(message: 'Le prix total doit être positif.')]
    private ?string $totalPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $cleaningFee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $serviceFee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $securityDeposit = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3, exactMessage: 'La devise doit être un code à 3 lettres (ex: EUR).')]
    private ?string $currency = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToOne(mappedBy: 'reservation', targetEntity: Invoice::class, cascade: ['persist', 'remove'])]
    private ?Invoice $invoice = null;

    /** @var Collection<int, ReservationStatusHistory> */
    #[ORM\OneToMany(targetEntity: ReservationStatusHistory::class, mappedBy: 'reservation', orphanRemoval: true)]
    private Collection $statusHistory;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'reservation')]
    private Collection $payments;

    /** @var Collection<int, Payout> */
    #[ORM\OneToMany(targetEntity: Payout::class, mappedBy: 'reservation')]
    private Collection $payouts;

    /** @var Collection<int, Dispute> */
    #[ORM\OneToMany(targetEntity: Dispute::class, mappedBy: 'reservation')]
    private Collection $disputes;

    /** @var Collection<int, Conversation> */
    #[ORM\OneToMany(targetEntity: Conversation::class, mappedBy: 'reservation')]
    private Collection $conversations;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'reservation')]
    private Collection $reviews;

    public function __construct()
    {
        $this->statusHistory = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->payouts = new ArrayCollection();
        $this->disputes = new ArrayCollection();
        $this->conversations = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getCheckinDate(): ?\DateTimeImmutable
    {
        return $this->checkinDate;
    }

    public function setCheckinDate(\DateTimeImmutable $checkinDate): static
    {
        $this->checkinDate = $checkinDate;

        return $this;
    }

    public function getCheckoutDate(): ?\DateTimeImmutable
    {
        return $this->checkoutDate;
    }

    public function setCheckoutDate(\DateTimeImmutable $checkoutDate): static
    {
        $this->checkoutDate = $checkoutDate;

        return $this;
    }

    public function getGuestsCount(): ?int
    {
        return $this->guestsCount;
    }

    public function setGuestsCount(int $guestsCount): static
    {
        $this->guestsCount = $guestsCount;

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

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    public function getCleaningFee(): ?string
    {
        return $this->cleaningFee;
    }

    public function setCleaningFee(?string $cleaningFee): static
    {
        $this->cleaningFee = $cleaningFee;

        return $this;
    }

    public function getServiceFee(): ?string
    {
        return $this->serviceFee;
    }

    public function setServiceFee(?string $serviceFee): static
    {
        $this->serviceFee = $serviceFee;

        return $this;
    }

    public function getSecurityDeposit(): ?string
    {
        return $this->securityDeposit;
    }

    public function setSecurityDeposit(?string $securityDeposit): static
    {
        $this->securityDeposit = $securityDeposit;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): static
    {
        $this->cancellationReason = $cancellationReason;

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

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        if ($invoice !== null && $invoice->getReservation() !== $this) {
            $invoice->setReservation($this);
        }
        $this->invoice = $invoice;

        return $this;
    }

    /** @return Collection<int, ReservationStatusHistory> */
    public function getStatusHistory(): Collection
    {
        return $this->statusHistory;
    }

    public function addStatusHistory(ReservationStatusHistory $statusHistory): static
    {
        if (!$this->statusHistory->contains($statusHistory)) {
            $this->statusHistory->add($statusHistory);
            $statusHistory->setReservation($this);
        }

        return $this;
    }

    public function removeStatusHistory(ReservationStatusHistory $statusHistory): static
    {
        $this->statusHistory->removeElement($statusHistory);

        return $this;
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setReservation($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        $this->payments->removeElement($payment);

        return $this;
    }

    /** @return Collection<int, Payout> */
    public function getPayouts(): Collection
    {
        return $this->payouts;
    }

    public function addPayout(Payout $payout): static
    {
        if (!$this->payouts->contains($payout)) {
            $this->payouts->add($payout);
            $payout->setReservation($this);
        }

        return $this;
    }

    public function removePayout(Payout $payout): static
    {
        $this->payouts->removeElement($payout);

        return $this;
    }

    /** @return Collection<int, Dispute> */
    public function getDisputes(): Collection
    {
        return $this->disputes;
    }

    public function addDispute(Dispute $dispute): static
    {
        if (!$this->disputes->contains($dispute)) {
            $this->disputes->add($dispute);
            $dispute->setReservation($this);
        }

        return $this;
    }

    public function removeDispute(Dispute $dispute): static
    {
        $this->disputes->removeElement($dispute);

        return $this;
    }

    /** @return Collection<int, Conversation> */
    public function getConversations(): Collection
    {
        return $this->conversations;
    }

    public function addConversation(Conversation $conversation): static
    {
        if (!$this->conversations->contains($conversation)) {
            $this->conversations->add($conversation);
            $conversation->setReservation($this);
        }

        return $this;
    }

    public function removeConversation(Conversation $conversation): static
    {
        $this->conversations->removeElement($conversation);

        return $this;
    }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setReservation($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        $this->reviews->removeElement($review);

        return $this;
    }
}
