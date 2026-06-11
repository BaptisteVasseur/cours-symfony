<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\PropertyICalSyncRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropertyICalSyncRepository::class)]
#[ORM\Table(name: 'property_ical_sync')]
#[ORM\UniqueConstraint(name: 'uniq_ical_sync_property_url', columns: ['property_id', 'i_cal_url'])]
class PropertyICalSync
{
    use UuidEntityTrait;

    #[ORM\ManyToOne(inversedBy: 'iCalSyncs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[ORM\Column(length: 100)]
    private ?string $providerName = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $iCalUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

    #[ORM\Column(length: 50)]
    private string $syncStatus = 'pending';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function setProviderName(string $providerName): static
    {
        $this->providerName = $providerName;

        return $this;
    }

    public function getICalUrl(): ?string
    {
        return $this->iCalUrl;
    }

    public function setICalUrl(string $iCalUrl): static
    {
        $this->iCalUrl = $iCalUrl;

        return $this;
    }

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeImmutable $lastSyncAt): static
    {
        $this->lastSyncAt = $lastSyncAt;

        return $this;
    }

    public function getSyncStatus(): string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(string $syncStatus): static
    {
        $this->syncStatus = $syncStatus;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }
}
