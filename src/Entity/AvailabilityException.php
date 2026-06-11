<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\AvailabilityExceptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvailabilityExceptionRepository::class)]
#[ORM\Table(name: 'availability_exceptions')]
#[ORM\UniqueConstraint(name: 'uniq_exception_property_date', columns: ['property_id', 'date'])]
class AvailabilityException
{
    use UuidEntityTrait;

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_ICAL_IMPORT = 'ical_import';
    public const SOURCE_RESERVATION = 'reservation';

    #[Assert\NotNull(message: 'Le logement est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'availabilityExceptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[Assert\NotNull(message: 'La date est obligatoire.')]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[Assert\Length(max: 255, maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[Assert\Choice(
        choices: [self::SOURCE_MANUAL, self::SOURCE_ICAL_IMPORT, self::SOURCE_RESERVATION],
        message: 'Source invalide.',
    )]
    #[ORM\Column(length: 50)]
    private string $source = self::SOURCE_MANUAL;

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

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
}
