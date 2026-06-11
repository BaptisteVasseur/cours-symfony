<?php

namespace App\Entity;

use App\Repository\ListingAvailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ListingAvailabilityRepository::class)]
class ListingAvailability
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Listing $listing = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $availableDate = null;

    #[ORM\Column]
    private bool $isAvailable = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $customPrice = null;

    #[ORM\Column(nullable: true)]
    private ?int $minimumStay = null;

    public function getId(): ?Uuid { return $this->id; }

    public function getListing(): ?Listing { return $this->listing; }
    public function setListing(?Listing $v): static { $this->listing = $v; return $this; }

    public function getAvailableDate(): ?\DateTimeImmutable { return $this->availableDate; }
    public function setAvailableDate(\DateTimeImmutable $v): static { $this->availableDate = $v; return $this; }

    public function isAvailable(): bool { return $this->isAvailable; }
    public function setIsAvailable(bool $v): static { $this->isAvailable = $v; return $this; }

    public function getCustomPrice(): ?string { return $this->customPrice; }
    public function setCustomPrice(?string $v): static { $this->customPrice = $v; return $this; }

    public function getMinimumStay(): ?int { return $this->minimumStay; }
    public function setMinimumStay(?int $v): static { $this->minimumStay = $v; return $this; }
}
