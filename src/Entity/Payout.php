<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\PayoutRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PayoutRepository::class)]
#[ORM\Table(name: 'payouts')]
class Payout
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'L\'hôte bénéficiaire est obligatoire.')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $host = null;

    #[Assert\NotNull(message: 'La réservation associée est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'payouts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

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
        choices: ['pending', 'processing', 'paid', 'failed', 'cancelled'],
        message: 'Le statut sélectionné n\'est pas valide.',
    )]
    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function getHost(): ?User
    {
        return $this->host;
    }

    public function setHost(?User $host): static
    {
        $this->host = $host;

        return $this;
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
}
