<?php

namespace App\Entity;

use App\Repository\PayoutRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PayoutRepository::class)]
class Payout
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $host = null;

    #[ORM\ManyToOne(inversedBy: 'payouts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $booking = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(length: 30)]
    private ?string $payoutStatus = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function getId(): ?Uuid { return $this->id; }

    public function getHost(): ?User { return $this->host; }
    public function setHost(?User $v): static { $this->host = $v; return $this; }

    public function getBooking(): ?Booking { return $this->booking; }
    public function setBooking(?Booking $v): static { $this->booking = $v; return $this; }

    public function getAmount(): ?string { return $this->amount; }
    public function setAmount(string $v): static { $this->amount = $v; return $this; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(string $v): static { $this->currency = $v; return $this; }

    public function getPayoutStatus(): ?string { return $this->payoutStatus; }
    public function setPayoutStatus(string $v): static { $this->payoutStatus = $v; return $this; }

    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; }
    public function setPaidAt(?\DateTimeImmutable $v): static { $this->paidAt = $v; return $this; }
}
