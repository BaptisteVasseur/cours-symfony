<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\PropertyICalSyncRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropertyICalSyncRepository::class)]
#[ORM\Table(name: 'property_ical_sync')]
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
}
