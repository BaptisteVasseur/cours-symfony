<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PropertyAvailabilityRepository::class)]
#[ORM\Table(name: 'property_availability')]
class PropertyAvailability
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'Le logement associé est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[Assert\NotNull(message: 'La date est obligatoire.')]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $availableDate = null;

    #[Assert\Type(type: 'bool')]
    #[ORM\Column]
    private bool $isAvailable = true;

    #[Assert\PositiveOrZero(message: 'Le prix de remplacement doit être supérieur ou égal à zéro.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $priceOverride = null;

    #[Assert\Positive(message: 'La durée minimale de séjour doit être supérieure à zéro.')]
    #[Assert\Range(
        notInRangeMessage: 'La durée minimale doit être comprise entre {{ min }} et {{ max }} nuits.',
        min: 1,
        max: 365,
    )]
    #[ORM\Column(nullable: true)]
    private ?int $minimumStay = null;

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
}
