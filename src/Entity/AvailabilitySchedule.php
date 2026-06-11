<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\AvailabilityScheduleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvailabilityScheduleRepository::class)]
#[ORM\Table(name: 'availability_schedules')]
#[ORM\Index(columns: ['property_id', 'start_date', 'end_date'], name: 'idx_schedule_property_dates')]
class AvailabilitySchedule
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'Le logement est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'availabilitySchedules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[Assert\NotNull(message: 'La date de début est obligatoire.')]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $startDate = null;

    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThan(propertyPath: 'startDate', message: 'La date de fin doit être postérieure à la date de début.')]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $daysOfWeek = null;

    #[Assert\NotNull(message: "L'heure d'arrivée est obligatoire.")]
    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $checkInTime = null;

    #[Assert\NotNull(message: "L'heure de départ est obligatoire.")]
    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $checkOutTime = null;

    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'La durée minimale doit être d\'au moins {{ compared_value }} nuit.')]
    #[ORM\Column]
    private int $minimumStay = 1;

    #[Assert\GreaterThanOrEqual(propertyPath: 'minimumStay', message: 'La durée maximale doit être supérieure ou égale à la durée minimale.')]
    #[ORM\Column(nullable: true)]
    private ?int $maximumStay = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

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

    public function getDaysOfWeek(): ?array
    {
        return $this->daysOfWeek;
    }

    public function setDaysOfWeek(?array $daysOfWeek): static
    {
        $this->daysOfWeek = $daysOfWeek;

        return $this;
    }

    public function getCheckInTime(): ?\DateTimeImmutable
    {
        return $this->checkInTime;
    }

    public function setCheckInTime(\DateTimeImmutable $checkInTime): static
    {
        $this->checkInTime = $checkInTime;

        return $this;
    }

    public function getCheckOutTime(): ?\DateTimeImmutable
    {
        return $this->checkOutTime;
    }

    public function setCheckOutTime(\DateTimeImmutable $checkOutTime): static
    {
        $this->checkOutTime = $checkOutTime;

        return $this;
    }

    public function getMinimumStay(): int
    {
        return $this->minimumStay;
    }

    public function setMinimumStay(int $minimumStay): static
    {
        $this->minimumStay = $minimumStay;

        return $this;
    }

    public function getMaximumStay(): ?int
    {
        return $this->maximumStay;
    }

    public function setMaximumStay(?int $maximumStay): static
    {
        $this->maximumStay = $maximumStay;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function coversDay(\DateTimeImmutable $day): bool
    {
        if ($day < $this->startDate || $day > $this->endDate) {
            return false;
        }

        if ($this->daysOfWeek === null) {
            return true;
        }

        return in_array((int) $day->format('N'), $this->daysOfWeek, true);
    }
}
