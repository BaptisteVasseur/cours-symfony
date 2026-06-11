<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\PaymentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
class Payment
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'La réservation est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    #[Assert\NotNull(message: 'Le payeur est obligatoire.')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $payer = null;

    #[Assert\NotBlank(message: 'Le prestataire de paiement est obligatoire.')]
    #[Assert\Choice(
        choices: ['stripe', 'paypal'],
        message: 'Le prestataire sélectionné n\'est pas valide.',
    )]
    #[ORM\Column(length: 50)]
    private ?string $provider = null;

    #[Assert\NotBlank(message: 'L\'identifiant de transaction est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'L\'identifiant ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 255)]
    private ?string $providerPaymentIntent = null;

    #[Assert\NotBlank(message: 'Le montant est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le montant doit être supérieur ou égal à zéro.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amount = null;

    #[Assert\NotBlank(message: 'La devise est obligatoire.')]
    #[Assert\Choice(
        choices: ['EUR', 'USD', 'GBP'],
        message: 'La devise sélectionnée n\'est pas valide.',
    )]
    #[Assert\Length(max: 10, maxMessage: 'La devise ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 10)]
    private ?string $currency = null;

    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['pending', 'authorized', 'captured', 'succeeded', 'failed', 'refunded', 'cancelled'],
        message: 'Le statut sélectionné n\'est pas valide.',
    )]
    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, Refund> */
    #[ORM\OneToMany(targetEntity: Refund::class, mappedBy: 'payment', orphanRemoval: true)]
    private Collection $refunds;

    public function __construct()
    {
        $this->refunds = new ArrayCollection();
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

    public function getPayer(): ?User
    {
        return $this->payer;
    }

    public function setPayer(?User $payer): static
    {
        $this->payer = $payer;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderPaymentIntent(): ?string
    {
        return $this->providerPaymentIntent;
    }

    public function setProviderPaymentIntent(string $providerPaymentIntent): static
    {
        $this->providerPaymentIntent = $providerPaymentIntent;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

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

    /** @return Collection<int, Refund> */
    public function getRefunds(): Collection
    {
        return $this->refunds;
    }

    public function addRefund(Refund $refund): static
    {
        if (!$this->refunds->contains($refund)) {
            $this->refunds->add($refund);
            $refund->setPayment($this);
        }

        return $this;
    }

    public function removeRefund(Refund $refund): static
    {
        $this->refunds->removeElement($refund);

        return $this;
    }
}
