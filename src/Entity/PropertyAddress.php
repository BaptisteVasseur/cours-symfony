<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\PropertyAddressRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PropertyAddressRepository::class)]
#[ORM\Table(name: 'property_addresses')]
class PropertyAddress
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'Le logement associé est obligatoire.')]
    #[ORM\OneToOne(inversedBy: 'address', targetEntity: Property::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[Assert\NotBlank(message: 'Le pays est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le pays ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 100)]
    private ?string $country = null;

    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 100)]
    private ?string $city = null;

    #[Assert\Length(max: 20, maxMessage: 'Le code postal ne peut pas dépasser {{ limit }} caractères.')]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9\s\-]{2,20}$/',
        message: 'Le code postal n\'est pas valide.',
    )]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[Assert\NotBlank(message: 'L\'adresse est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 255)]
    private ?string $addressLine1 = null;

    #[Assert\Length(max: 255, maxMessage: 'Le complément d\'adresse ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $addressLine2 = null;

    #[Assert\Range(
        notInRangeMessage: 'La latitude doit être comprise entre {{ min }} et {{ max }}.',
        min: -90,
        max: 90,
    )]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[Assert\Range(
        notInRangeMessage: 'La longitude doit être comprise entre {{ min }} et {{ max }}.',
        min: -180,
        max: 180,
    )]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getAddressLine1(): ?string
    {
        return $this->addressLine1;
    }

    public function setAddressLine1(string $addressLine1): static
    {
        $this->addressLine1 = $addressLine1;

        return $this;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function setAddressLine2(?string $addressLine2): static
    {
        $this->addressLine2 = $addressLine2;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }
}
