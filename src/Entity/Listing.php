<?php

namespace App\Entity;

use App\Repository\ListingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ListingRepository::class)]
class Listing
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'listings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $host = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(min: 5, max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $propertyType = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $roomType = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'Le nombre de voyageurs doit être positif.')]
    private ?int $maxGuests = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $bedrooms = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $beds = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $bathrooms = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $channel = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix par nuit est obligatoire.')]
    #[Assert\Positive(message: 'Le prix par nuit doit être supérieur à 0.')]
    private ?string $pricePerNight = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $cleaningFee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $serviceFee = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $status = null;

    #[ORM\Column]
    private bool $instantBooking = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cancellationPolicy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(mappedBy: 'listing', targetEntity: ListingLocation::class)]
    private ?ListingLocation $location = null;

    #[ORM\OneToMany(mappedBy: 'listing', targetEntity: ListingPhoto::class)]
    private Collection $photos;

    #[ORM\OneToMany(mappedBy: 'listing', targetEntity: ListingAvailability::class)]
    private Collection $availabilities;

    #[ORM\OneToMany(mappedBy: 'listing', targetEntity: Booking::class)]
    private Collection $bookings;

    #[ORM\OneToMany(mappedBy: 'listing', targetEntity: Review::class)]
    private Collection $reviews;

    #[ORM\ManyToMany(targetEntity: Amenity::class, inversedBy: 'listings')]
    #[ORM\JoinTable(name: 'listing_amenities')]
    private Collection $amenities;

    #[ORM\OneToMany(mappedBy: 'listing', targetEntity: AvailabilityBlock::class, orphanRemoval: true)]
    private Collection $availabilityBlocks;

    #[ORM\Column(length: 64, unique: true)]
    private string $calendarToken;

    #[ORM\Column(length: 1024, nullable: true)]
    #[Assert\Url(message: 'L\'URL iCal n\'est pas valide.')]
    private ?string $icalImportUrl = null;

    public function __construct()
    {
        $this->photos = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->amenities = new ArrayCollection();
        $this->availabilityBlocks = new ArrayCollection();
        $this->calendarToken = self::generateCalendarToken();
    }

    public static function generateCalendarToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getHost(): ?User { return $this->host; }
    public function setHost(?User $v): static { $this->host = $v; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function getPropertyType(): ?string { return $this->propertyType; }
    public function setPropertyType(?string $v): static { $this->propertyType = $v; return $this; }

    public function getRoomType(): ?string { return $this->roomType; }
    public function setRoomType(?string $v): static { $this->roomType = $v; return $this; }

    public function getMaxGuests(): ?int { return $this->maxGuests; }
    public function setMaxGuests(?int $v): static { $this->maxGuests = $v; return $this; }

    public function getBedrooms(): ?int { return $this->bedrooms; }
    public function setBedrooms(?int $v): static { $this->bedrooms = $v; return $this; }

    public function getBeds(): ?int { return $this->beds; }
    public function setBeds(?int $v): static { $this->beds = $v; return $this; }

    public function getBathrooms(): ?int { return $this->bathrooms; }
    public function setBathrooms(?int $v): static { $this->bathrooms = $v; return $this; }

    public function getChannel(): ?string { return $this->channel; }
    public function setChannel(?string $v): static { $this->channel = $v; return $this; }

    public function getPricePerNight(): ?string { return $this->pricePerNight; }
    public function setPricePerNight(string $v): static { $this->pricePerNight = $v; return $this; }

    public function getCleaningFee(): ?string { return $this->cleaningFee; }
    public function setCleaningFee(?string $v): static { $this->cleaningFee = $v; return $this; }

    public function getServiceFee(): ?string { return $this->serviceFee; }
    public function setServiceFee(?string $v): static { $this->serviceFee = $v; return $this; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): static { $this->currency = $v; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(?string $v): static { $this->status = $v; return $this; }

    public function isInstantBooking(): bool { return $this->instantBooking; }
    public function setInstantBooking(bool $v): static { $this->instantBooking = $v; return $this; }

    public function getCancellationPolicy(): ?string { return $this->cancellationPolicy; }
    public function setCancellationPolicy(?string $v): static { $this->cancellationPolicy = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $v): static { $this->updatedAt = $v; return $this; }

    public function getLocation(): ?ListingLocation { return $this->location; }

    public function getPhotos(): Collection { return $this->photos; }

    public function getAvailabilities(): Collection { return $this->availabilities; }

    public function getBookings(): Collection { return $this->bookings; }

    public function getReviews(): Collection { return $this->reviews; }

    public function getAmenities(): Collection { return $this->amenities; }

    public function addAmenity(Amenity $amenity): static
    {
        if (!$this->amenities->contains($amenity)) {
            $this->amenities->add($amenity);
        }
        return $this;
    }

    public function removeAmenity(Amenity $amenity): static
    {
        $this->amenities->removeElement($amenity);
        return $this;
    }

    public function getAvailabilityBlocks(): Collection { return $this->availabilityBlocks; }

    public function addAvailabilityBlock(AvailabilityBlock $block): static
    {
        if (!$this->availabilityBlocks->contains($block)) {
            $this->availabilityBlocks->add($block);
            $block->setListing($this);
        }
        return $this;
    }

    public function removeAvailabilityBlock(AvailabilityBlock $block): static
    {
        $this->availabilityBlocks->removeElement($block);
        return $this;
    }

    public function getCalendarToken(): string { return $this->calendarToken; }
    public function setCalendarToken(string $v): static { $this->calendarToken = $v; return $this; }
    public function regenerateCalendarToken(): static { $this->calendarToken = self::generateCalendarToken(); return $this; }

    public function getIcalImportUrl(): ?string { return $this->icalImportUrl; }
    public function setIcalImportUrl(?string $v): static { $this->icalImportUrl = $v; return $this; }

    public function isPublished(): bool { return $this->status === 'published'; }
}
