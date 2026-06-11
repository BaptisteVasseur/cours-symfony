<?php

namespace App\Entity;

use App\Repository\DisputeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DisputeRepository::class)]
class Dispute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resolution = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $booking = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $raisedBy = null;

    public function getId(): ?int { return $this->id; }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(string $reason): static { $this->reason = $reason; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getResolution(): ?string { return $this->resolution; }
    public function setResolution(?string $resolution): static { $this->resolution = $resolution; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getBooking(): ?Booking { return $this->booking; }
    public function setBooking(?Booking $booking): static { $this->booking = $booking; return $this; }

    public function getRaisedBy(): ?User { return $this->raisedBy; }
    public function setRaisedBy(?User $raisedBy): static { $this->raisedBy = $raisedBy; return $this; }
}
