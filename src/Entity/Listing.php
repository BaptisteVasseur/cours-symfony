<?php

namespace App\Entity;

use App\Repository\ListingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ListingRepository::class)]
class Listing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $pricePerNight = null;

    #[ORM\Column]
    private ?int $maxGuests = null;

    #[ORM\Column]
    private ?int $bedrooms = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 1)]
    private ?string $bathrooms = null;

    #[ORM\Column(length: 100)]
    private ?string $propertyType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[ORM\Column(length: 255)]
    private ?string $country = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'listings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $host = null;

    #[ORM\ManyToOne(inversedBy: 'listings')]
    private ?CancellationPolicy $cancellationPolicy = null;

    /** @var Collection<int, Booking> */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'listing')]
    private Collection $bookings;

    /** @var Collection<int, ListingPhoto> */
    #[ORM\OneToMany(targetEntity: ListingPhoto::class, mappedBy: 'listing', cascade: ['persist', 'remove'])]
    private Collection $photos;

    /** @var Collection<int, Amenity> */
    #[ORM\ManyToMany(targetEntity: Amenity::class, inversedBy: 'listings')]
    #[ORM\JoinTable(name: 'listing_amenities')]
    private Collection $amenities;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'listing')]
    private Collection $reviews;

    /** @var Collection<int, Conversation> */
    #[ORM\OneToMany(targetEntity: Conversation::class, mappedBy: 'listing')]
    private Collection $conversations;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
        $this->photos = new ArrayCollection();
        $this->amenities = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->conversations = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getPricePerNight(): ?string { return $this->pricePerNight; }
    public function setPricePerNight(string $pricePerNight): static { $this->pricePerNight = $pricePerNight; return $this; }

    public function getMaxGuests(): ?int { return $this->maxGuests; }
    public function setMaxGuests(int $maxGuests): static { $this->maxGuests = $maxGuests; return $this; }

    public function getBedrooms(): ?int { return $this->bedrooms; }
    public function setBedrooms(int $bedrooms): static { $this->bedrooms = $bedrooms; return $this; }

    public function getBathrooms(): ?string { return $this->bathrooms; }
    public function setBathrooms(string $bathrooms): static { $this->bathrooms = $bathrooms; return $this; }

    public function getPropertyType(): ?string { return $this->propertyType; }
    public function setPropertyType(string $propertyType): static { $this->propertyType = $propertyType; return $this; }

    public function getLatitude(): ?string { return $this->latitude; }
    public function setLatitude(?string $latitude): static { $this->latitude = $latitude; return $this; }

    public function getLongitude(): ?string { return $this->longitude; }
    public function setLongitude(?string $longitude): static { $this->longitude = $longitude; return $this; }

    public function getCity(): ?string { return $this->city; }
    public function setCity(string $city): static { $this->city = $city; return $this; }

    public function getCountry(): ?string { return $this->country; }
    public function setCountry(string $country): static { $this->country = $country; return $this; }

    public function isActive(): ?bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getHost(): ?User { return $this->host; }
    public function setHost(?User $host): static { $this->host = $host; return $this; }

    public function getCancellationPolicy(): ?CancellationPolicy { return $this->cancellationPolicy; }
    public function setCancellationPolicy(?CancellationPolicy $cancellationPolicy): static { $this->cancellationPolicy = $cancellationPolicy; return $this; }

    /** @return Collection<int, Booking> */
    public function getBookings(): Collection { return $this->bookings; }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setListing($this);
        }
        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking) && $booking->getListing() === $this) {
            $booking->setListing(null);
        }
        return $this;
    }

    /** @return Collection<int, ListingPhoto> */
    public function getPhotos(): Collection { return $this->photos; }

    public function addPhoto(ListingPhoto $photo): static
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setListing($this);
        }
        return $this;
    }

    public function removePhoto(ListingPhoto $photo): static
    {
        if ($this->photos->removeElement($photo) && $photo->getListing() === $this) {
            $photo->setListing(null);
        }
        return $this;
    }

    /** @return Collection<int, Amenity> */
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

    /** @return Collection<int, Review> */
    public function getReviews(): Collection { return $this->reviews; }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setListing($this);
        }
        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review) && $review->getListing() === $this) {
            $review->setListing(null);
        }
        return $this;
    }

    /** @return Collection<int, Conversation> */
    public function getConversations(): Collection { return $this->conversations; }

    public function addConversation(Conversation $conversation): static
    {
        if (!$this->conversations->contains($conversation)) {
            $this->conversations->add($conversation);
            $conversation->setListing($this);
        }
        return $this;
    }

    public function removeConversation(Conversation $conversation): static
    {
        if ($this->conversations->removeElement($conversation) && $conversation->getListing() === $this) {
            $conversation->setListing(null);
        }
        return $this;
    }
}
