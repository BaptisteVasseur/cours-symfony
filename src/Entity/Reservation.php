<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Trait\UuidEntityTrait;
use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('RESERVATION_VIEW', object)"),
        new Post(
            security: "is_granted('ROLE_USER')",
            securityPostDenormalize: "object.getGuest() == user",
        ),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
)]
#[Assert\Expression(
    expression: "this.getStatus() != 'cancelled' or (this.getCancellationReason() !== null and this.getCancellationReason() !== '')",
    message: 'Le motif d\'annulation est obligatoire pour une réservation annulée.',
)]
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservations')]
#[ORM\Index(name: 'idx_reservation_availability', columns: ['property_id', 'status', 'checkin_date', 'checkout_date'])]
class Reservation
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'Le logement est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[Assert\NotNull(message: 'Le voyageur est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $guest = null;

    #[Assert\NotNull(message: 'La date d\'arrivée est obligatoire.')]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $checkinDate = null;

    #[Assert\NotNull(message: 'La date de départ est obligatoire.')]
    #[Assert\GreaterThan(propertyPath: 'checkinDate', message: 'La date de départ doit être postérieure à la date d\'arrivée.')]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $checkoutDate = null;

    #[Assert\NotNull(message: 'Le nombre de voyageurs est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'Il doit y avoir au moins {{ compared_value }} voyageur.')]
    #[ORM\Column]
    private ?int $guestsCount = null;

    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['pending', 'confirmed', 'completed', 'cancelled'],
        message: 'Le statut sélectionné n\'est pas valide.',
    )]
    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[Assert\NotBlank(message: 'Le prix total est obligatoire.')]
    #[Assert\Type(type: 'numeric', message: 'Le prix total doit être un nombre.')]
    #[Assert\Positive(message: 'Le prix total doit être supérieur à 0.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalPrice = null;

    #[Assert\Type(type: 'numeric', message: 'Les frais de ménage doivent être un nombre.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Les frais de ménage ne peuvent pas être négatifs.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $cleaningFee = null;

    #[Assert\Type(type: 'numeric', message: 'Les frais de service doivent être un nombre.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Les frais de service ne peuvent pas être négatifs.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $serviceFee = null;

    #[Assert\Type(type: 'numeric', message: 'La caution doit être un nombre.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'La caution ne peut pas être négative.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $securityDeposit = null;

    #[Assert\NotBlank(message: 'La devise est obligatoire.')]
    #[Assert\Choice(
        choices: ['EUR', 'USD', 'GBP'],
        message: 'La devise sélectionnée n\'est pas valide.',
    )]
    #[ORM\Column(length: 10)]
    private ?string $currency = null;

    #[Assert\Length(max: 2000, maxMessage: 'Le motif d\'annulation ne peut pas dépasser {{ limit }} caractères.')]
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
