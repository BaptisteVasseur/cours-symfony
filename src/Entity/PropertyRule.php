<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\PropertyRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropertyRuleRepository::class)]
#[ORM\Table(name: 'property_rules')]
class PropertyRule
{
    use UuidEntityTrait;

    #[ORM\OneToOne(inversedBy: 'rules', targetEntity: Property::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[ORM\Column]
    private bool $petsAllowed = false;

    #[ORM\Column]
    private bool $smokingAllowed = false;

    #[ORM\Column]
    private bool $partiesAllowed = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $additionalRules = null;

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function isPetsAllowed(): bool
    {
        return $this->petsAllowed;
    }

    public function setPetsAllowed(bool $petsAllowed): static
    {
        $this->petsAllowed = $petsAllowed;

        return $this;
    }

    public function isSmokingAllowed(): bool
    {
        return $this->smokingAllowed;
    }

    public function setSmokingAllowed(bool $smokingAllowed): static
    {
        $this->smokingAllowed = $smokingAllowed;

        return $this;
    }

    public function isPartiesAllowed(): bool
    {
        return $this->partiesAllowed;
    }

    public function setPartiesAllowed(bool $partiesAllowed): static
    {
        $this->partiesAllowed = $partiesAllowed;

        return $this;
    }

    public function getAdditionalRules(): ?string
    {
        return $this->additionalRules;
    }

    public function setAdditionalRules(?string $additionalRules): static
    {
        $this->additionalRules = $additionalRules;

        return $this;
    }
}
