<?php

namespace App\Entity;

use App\Enum\PropertyStatus;
use App\Repository\PropertyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PropertyRepository::class)]
#[ORM\Table(name: 'properties')]
class Property
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'properties')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $host = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $address = null;

    #[ORM\Column(length: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 100)]
    private ?string $country = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $pricePerNight = null;

    #[ORM\Column]
    private ?int $maxGuests = null;

    #[ORM\Column(enumType: PropertyStatus::class)]
    private PropertyStatus $status = PropertyStatus::DRAFT;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: PropertyImage::class, mappedBy: 'property', cascade: ['persist', 'remove'])]
    private Collection $images;

    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'property')]
    private Collection $bookings;

    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'property')]
    private Collection $reviews;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getHost(): ?User
    {
        return $this->host;
    }

    public function setHost(?User $host): static
    {
        $this->host = $host;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;
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

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;
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

    public function getPricePerNight(): ?string
    {
        return $this->pricePerNight;
    }

    public function setPricePerNight(string $pricePerNight): static
    {
        $this->pricePerNight = $pricePerNight;
        return $this;
    }

    public function getMaxGuests(): ?int
    {
        return $this->maxGuests;
    }

    public function setMaxGuests(int $maxGuests): static
    {
        $this->maxGuests = $maxGuests;
        return $this;
    }

    public function getStatus(): PropertyStatus
    {
        return $this->status;
    }

    public function setStatus(PropertyStatus $status): static
    {
        $this->status = $status;
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

    /** @return Collection<int, PropertyImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(PropertyImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProperty($this);
        }
        return $this;
    }

    public function removeImage(PropertyImage $image): static
    {
        $this->images->removeElement($image);
        return $this;
    }

    /** @return Collection<int, Booking> */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function getAverageRating(): ?float
    {
        if ($this->reviews->isEmpty()) {
            return null;
        }
        $total = array_sum($this->reviews->map(fn(Review $r) => $r->getRating())->toArray());
        return round($total / $this->reviews->count(), 1);
    }
}
