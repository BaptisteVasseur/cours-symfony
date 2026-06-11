<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\AmenityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AmenityRepository::class)]
#[ORM\Table(name: 'amenities')]
class Amenity
{
    use UuidEntityTrait;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $label = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $category = null;

    /** @var Collection<int, PropertyAmenity> */
    #[ORM\OneToMany(targetEntity: PropertyAmenity::class, mappedBy: 'amenity', orphanRemoval: true)]
    private Collection $propertyAmenities;

    public function __construct()
    {
        $this->propertyAmenities = new ArrayCollection();
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;

        return $this;
    }

    /** @return Collection<int, PropertyAmenity> */
    public function getPropertyAmenities(): Collection
    {
        return $this->propertyAmenities;
    }

    public function addPropertyAmenity(PropertyAmenity $propertyAmenity): static
    {
        if (!$this->propertyAmenities->contains($propertyAmenity)) {
            $this->propertyAmenities->add($propertyAmenity);
            $propertyAmenity->setAmenity($this);
        }

        return $this;
    }

    public function removePropertyAmenity(PropertyAmenity $propertyAmenity): static
    {
        $this->propertyAmenities->removeElement($propertyAmenity);

        return $this;
    }
}
