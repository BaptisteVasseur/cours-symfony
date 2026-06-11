<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Enum\BookingStatus;
use App\Repository\ReservationStatusHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationStatusHistoryRepository::class)]
#[ORM\Table(name: 'reservation_status_history')]
class ReservationStatusHistory
{
    use UuidEntityTrait;

    #[ORM\ManyToOne(inversedBy: 'statusHistory')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Reservation $reservation = null;

    #[ORM\Column(enumType: BookingStatus::class, nullable: true)]
    private ?BookingStatus $fromStatus = null;

    #[ORM\Column(enumType: BookingStatus::class)]
    private ?BookingStatus $toStatus = null;

    // 'guest' | 'host' | 'system'
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $actor = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getFromStatus(): ?BookingStatus
    {
        return $this->fromStatus;
    }

    public function setFromStatus(?BookingStatus $fromStatus): static
    {
        $this->fromStatus = $fromStatus;

        return $this;
    }

    public function getToStatus(): ?BookingStatus
    {
        return $this->toStatus;
    }

    public function setToStatus(?BookingStatus $toStatus): static
    {
        $this->toStatus = $toStatus;

        return $this;
    }

    public function getActor(): ?string
    {
        return $this->actor;
    }

    public function setActor(?string $actor): static
    {
        $this->actor = $actor;

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
