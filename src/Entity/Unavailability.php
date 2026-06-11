<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\UnavailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UnavailabilityRepository::class)]
#[ORM\Table(name: 'unavailabilities')]
#[ORM\Index(columns: ['property_id', 'start_date', 'end_date'], name: 'idx_unavailability_property_dates')]
class Unavailability
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'Le logement est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'unavailabilities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[Assert\NotNull(message: 'La date de début est obligatoire.')]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $startDate = null;

    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThan(propertyPath: 'startDate', message: 'La date de fin doit être postérieure à la date de début.')]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $endDate = null;

    #[Assert\Length(max: 1000, maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[Assert\Choice(choices: ['manual', 'ical_import'], message: 'La source sélectionnée n\'est pas valide.')]
    #[ORM\Column(length: 32)]
    private string $source = 'manual';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalUid = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
