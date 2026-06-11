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
#[ORM\Index(name: 'idx_availability_property_dates', columns: ['property_id', 'start_date', 'end_date'])]
class PropertyAvailability
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'Le logement est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[Assert\NotNull(message: 'La date de début est obligatoire.')]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $startDate = null;

    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDate', message: 'La date de fin doit être postérieure ou égale à la date de début.')]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column]
    private bool $isAvailable = true;

    #[Assert\Length(max: 500, maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $blockNote = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $priceOverride = null;

    #[ORM\Column(nullable: true)]
    private ?int $minimumStay = null;

    /**
     * Flux iCal d'origine si ce blocage a été importé (null = blocage manuel de l'hôte).
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?PropertyICalSync $icalSync = null;

    /**
     * UID de l'évènement iCal distant, pour le rapprochement lors des synchronisations.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalUid = null;

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

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

    public function getBlockNote(): ?string
    {
        return $this->blockNote;
    }

    public function setBlockNote(?string $blockNote): static
    {
        $this->blockNote = $blockNote;

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

    public function getIcalSync(): ?PropertyICalSync
    {
        return $this->icalSync;
    }

    public function setIcalSync(?PropertyICalSync $icalSync): static
    {
        $this->icalSync = $icalSync;

        return $this;
    }

    public function getExternalUid(): ?string
    {
        return $this->externalUid;
    }

    public function setExternalUid(?string $externalUid): static
    {
        $this->externalUid = $externalUid;

        return $this;
    }

    public function getNightsCount(): int
    {
        if ($this->startDate === null || $this->endDate === null) {
            return 0;
        }

        return (int) $this->startDate->diff($this->endDate)->days + 1;
    }
}
