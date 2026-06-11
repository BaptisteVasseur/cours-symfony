<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropertyAvailabilityRepository::class)]
#[ORM\Table(name: 'property_availability')]
class PropertyAvailability
{
    use UuidEntityTrait;

    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $availableDate = null;

    #[ORM\Column]
    private bool $isAvailable = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $priceOverride = null;

    #[ORM\Column(nullable: true)]
    private ?int $minimumStay = null;

    #[ORM\Column(length: 20, options: ['default' => 'manual'])]
    private string $source = 'manual';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceUid = null;

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getAvailableDate(): ?\DateTimeImmutable
    {
        return $this->availableDate;
    }

    public function setAvailableDate(\DateTimeImmutable $availableDate): static
    {
        $this->availableDate = $availableDate;

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;

        return $this;
    }

    public function getPriceOverride(): ?string
    {
        return $this->priceOverride;
    }

    public function setPriceOverride(?string $priceOverride): static
    {
        $this->priceOverride = $priceOverride;

        return $this;
    }

    public function getMinimumStay(): ?int
    {
        return $this->minimumStay;
    }

    public function setMinimumStay(?int $minimumStay): static
    {
        $this->minimumStay = $minimumStay;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getSourceUid(): ?string
    {
        return $this->sourceUid;
    }

    public function setSourceUid(?string $sourceUid): static
    {
        $this->sourceUid = $sourceUid;

        return $this;
    }
}
