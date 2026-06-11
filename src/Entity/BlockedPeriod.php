<?php

namespace App\Entity;

use App\Repository\BlockedPeriodRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlockedPeriodRepository::class)]
class BlockedPeriod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'blockedPeriods')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Listing $listing = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $endDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getListing(): ?Listing { return $this->listing; }
    public function setListing(?Listing $listing): static { $this->listing = $listing; return $this; }

    public function getStartDate(): ?\DateTime { return $this->startDate; }
    public function setStartDate(\DateTime $startDate): static { $this->startDate = $startDate; return $this; }

    public function getEndDate(): ?\DateTime { return $this->endDate; }
    public function setEndDate(\DateTime $endDate): static { $this->endDate = $endDate; return $this; }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
