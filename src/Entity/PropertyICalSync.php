<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\PropertyICalSyncRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PropertyICalSyncRepository::class)]
#[ORM\Table(name: 'property_ical_sync')]
class PropertyICalSync
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'Le logement associé est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'iCalSyncs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[Assert\NotBlank(message: 'Le nom du fournisseur est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom du fournisseur ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 100)]
    private ?string $providerName = null;

    #[Assert\NotBlank(message: 'L\'URL iCal est obligatoire.')]
    #[Assert\Url(
        message: 'Le lien iCal n\'est pas une URL valide.',
        protocols: ['http', 'https', 'webcal'],
    )]
    #[Assert\Length(max: 2048, maxMessage: 'L\'URL iCal ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $iCalUrl = null;

    #[Assert\Type(type: \DateTimeImmutable::class)]
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
