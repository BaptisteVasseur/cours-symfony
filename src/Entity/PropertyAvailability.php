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
#[ORM\Index(name: 'idx_property_availability_period', columns: ['property_id', 'date_start', 'date_end'])]
#[ORM\Index(name: 'idx_property_availability_ical_sync', columns: ['i_cal_sync_id', 'external_uid'])]
#[ORM\Index(name: 'idx_property_availability_source', columns: ['source'])]
class PropertyAvailability
{
    use UuidEntityTrait;

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_ICAL = 'ical';

    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $availableDate = null;

    #[Assert\NotNull(message: 'La date de debut est obligatoire.')]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateStart = null;

    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThan(propertyPath: 'dateStart', message: 'La date de fin doit etre posterieure a la date de debut.')]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateEnd = null;

    #[ORM\Column]
    private bool $isAvailable = true;

    #[Assert\Length(max: 2000)]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[Assert\Choice(choices: [self::SOURCE_MANUAL, self::SOURCE_ICAL])]
    #[ORM\Column(length: 20)]
    private string $source = self::SOURCE_MANUAL;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalUid = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?PropertyICalSync $iCalSync = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $priceOverride = null;

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
        $this->dateStart ??= $availableDate;
        $this->dateEnd ??= $availableDate->modify('+1 day');

        return $this;
    }

    public function getDateStart(): ?\DateTimeImmutable
    {
        return $this->dateStart ?? $this->availableDate;
    }

    public function setDateStart(\DateTimeImmutable $dateStart): static
    {
        $this->dateStart = $dateStart;
        $this->availableDate ??= $dateStart;

        return $this;
    }

    public function getDateEnd(): ?\DateTimeImmutable
    {
        return $this->dateEnd ?? $this->availableDate?->modify('+1 day');
    }

    public function setDateEnd(\DateTimeImmutable $dateEnd): static
    {
        $this->dateEnd = $dateEnd;

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

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

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

    public function getExternalUid(): ?string
    {
        return $this->externalUid;
    }

    public function setExternalUid(?string $externalUid): static
    {
        $this->externalUid = $externalUid;

        return $this;
    }

    public function getICalSync(): ?PropertyICalSync
    {
        return $this->iCalSync;
    }

    public function setICalSync(?PropertyICalSync $iCalSync): static
    {
        $this->iCalSync = $iCalSync;

        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

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
