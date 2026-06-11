<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PropertyAmenityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropertyAmenityRepository::class)]
#[ORM\Table(name: 'property_amenities')]
class PropertyAmenity
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'propertyAmenities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'propertyAmenities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Amenity $amenity = null;

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getAmenity(): ?Amenity
    {
        return $this->amenity;
    }

    public function setAmenity(?Amenity $amenity): static
    {
        $this->amenity = $amenity;

        return $this;
    }
}
