<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Trait\UuidEntityTrait;
use App\Repository\PropertyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('PROPERTY_VIEW', object)"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['mon-groupe']],
    denormalizationContext: ['groups' => ['mon-groupe-2']],
)]
#[ORM\Entity(repositoryClass: PropertyRepository::class)]
#[ORM\Table(name: 'properties')]
class Property
{
    use UuidEntityTrait;

    #[Groups(['mon-groupe'])]
    #[Assert\NotNull(message: 'L\'hôte est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'properties')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $host = null;

    #[Assert\NotNull(message: 'La politique d\'annulation est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'properties')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CancellationPolicy $cancellationPolicy = null;

    #[Groups(['mon-groupe', 'mon-groupe-2'])]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[Groups(['mon-groupe'])]
    #[Assert\Length(
        min: 10,
        max: 5000,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[Assert\NotBlank(message: 'Le type de logement est obligatoire.')]
    #[Assert\Choice(
        choices: ['villa', 'loft', 'apartment', 'house', 'chalet'],
        message: 'Le type de logement sélectionné n\'est pas valide.',
    )]
    #[ORM\Column(length: 50)]
    private ?string $propertyType = null;

    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['draft', 'pending', 'published'],
        message: 'Le statut sélectionné n\'est pas valide.',
    )]
    #[ORM\Column(length: 50)]
    private ?string $status = 'pending';

    #[Assert\NotNull(message: 'Le nombre de voyageurs est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'Il doit y avoir au moins {{ compared_value }} voyageur.')]
    #[ORM\Column]
    private ?int $maxGuests = null;

    #[Assert\NotNull(message: 'Le nombre de chambres est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le nombre de chambres ne peut pas être négatif.')]
    #[ORM\Column]
    private ?int $bedrooms = null;

    #[Assert\NotNull(message: 'Le nombre de lits est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'Il doit y avoir au moins {{ compared_value }} lit.')]
    #[ORM\Column]
    private ?int $beds = null;

    #[Assert\NotNull(message: 'Le nombre de salles de bain est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'Il doit y avoir au moins {{ compared_value }} salle de bain.')]
    #[ORM\Column]
    private ?int $bathrooms = null;

    #[Assert\NotBlank(message: 'Le prix par nuit est obligatoire.')]
    #[Assert\Type(type: 'numeric', message: 'Le prix par nuit doit être un nombre.')]
    #[Assert\Positive(message: 'Le prix par nuit doit être supérieur à 0.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $pricePerNight = null;

    #[Assert\Type(type: 'numeric', message: 'Les frais de ménage doivent être un nombre.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Les frais de ménage ne peuvent pas être négatifs.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $cleaningFee = null;

    #[Assert\Type(type: 'numeric', message: 'La caution doit être un nombre.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'La caution ne peut pas être négative.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $securityDeposit = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $checkinTime = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $checkoutTime = null;

    #[ORM\Column]
    private bool $instantBooking = false;

    #[ORM\Column(nullable: true)]
    private ?int $minStayNights = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(mappedBy: 'property', targetEntity: PropertyAddress::class, cascade: ['persist', 'remove'])]
    private ?PropertyAddress $address = null;

    #[ORM\OneToOne(mappedBy: 'property', targetEntity: PropertyRule::class, cascade: ['persist', 'remove'])]
    private ?PropertyRule $rules = null;

    /** @var Collection<int, PropertyAmenity> */
    #[ORM\OneToMany(targetEntity: PropertyAmenity::class, mappedBy: 'property', orphanRemoval: true)]
    private Collection $propertyAmenities;

    /** @var Collection<int, PropertyMedia> */
    #[ORM\OneToMany(targetEntity: PropertyMedia::class, mappedBy: 'property', orphanRemoval: true)]
    private Collection $media;

    /** @var Collection<int, PropertyAvailability> */
    #[ORM\OneToMany(targetEntity: PropertyAvailability::class, mappedBy: 'property', orphanRemoval: true)]
    private Collection $availabilities;

    /** @var Collection<int, AvailabilityBlock> */
    #[ORM\OneToMany(targetEntity: AvailabilityBlock::class, mappedBy: 'property', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $availabilityBlocks;

    /** @var Collection<int, PropertyICalSync> */
    #[ORM\OneToMany(targetEntity: PropertyICalSync::class, mappedBy: 'property', orphanRemoval: true)]
    private Collection $iCalSyncs;

    /** @var Collection<int, Reservation> */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'property')]
    private Collection $reservations;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'property')]
    private Collection $reviews;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'favoriteProperties')]
    private Collection $favoritedBy;

    public function __construct()
    {
        $this->propertyAmenities = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
        $this->availabilityBlocks = new ArrayCollection();
        $this->iCalSyncs = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->favoritedBy = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getCancellationPolicy(): ?CancellationPolicy
    {
        return $this->cancellationPolicy;
    }

    public function setCancellationPolicy(?CancellationPolicy $cancellationPolicy): static
    {
        $this->cancellationPolicy = $cancellationPolicy;

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

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPropertyType(): ?string
    {
        return $this->propertyType;
    }

    public function setPropertyType(string $propertyType): static
    {
        $this->propertyType = $propertyType;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    public function getBedrooms(): ?int
    {
        return $this->bedrooms;
    }

    public function setBedrooms(int $bedrooms): static
    {
        $this->bedrooms = $bedrooms;

        return $this;
    }

    public function getBeds(): ?int
    {
        return $this->beds;
    }

    public function setBeds(int $beds): static
    {
        $this->beds = $beds;

        return $this;
    }

    public function getBathrooms(): ?int
    {
        return $this->bathrooms;
    }

    public function setBathrooms(int $bathrooms): static
    {
        $this->bathrooms = $bathrooms;

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

    public function getCleaningFee(): ?string
    {
        return $this->cleaningFee;
    }

    public function setCleaningFee(?string $cleaningFee): static
    {
        $this->cleaningFee = $cleaningFee;

        return $this;
    }

    public function getSecurityDeposit(): ?string
    {
        return $this->securityDeposit;
    }

    public function setSecurityDeposit(?string $securityDeposit): static
    {
        $this->securityDeposit = $securityDeposit;

        return $this;
    }

    public function getCheckinTime(): ?\DateTimeImmutable
    {
        return $this->checkinTime;
    }

    public function setCheckinTime(?\DateTimeImmutable $checkinTime): static
    {
        $this->checkinTime = $checkinTime;

        return $this;
    }

    public function getCheckoutTime(): ?\DateTimeImmutable
    {
        return $this->checkoutTime;
    }

    public function setCheckoutTime(?\DateTimeImmutable $checkoutTime): static
    {
        $this->checkoutTime = $checkoutTime;

        return $this;
    }

    public function isInstantBooking(): bool
    {
        return $this->instantBooking;
    }

    public function setInstantBooking(bool $instantBooking): static
    {
        $this->instantBooking = $instantBooking;

        return $this;
    }

    public function getMinStayNights(): ?int
    {
        return $this->minStayNights;
    }

    public function setMinStayNights(?int $minStayNights): static
    {
        $this->minStayNights = $minStayNights;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getAddress(): ?PropertyAddress
    {
        return $this->address;
    }

    public function setAddress(?PropertyAddress $address): static
    {
        if ($address !== null && $address->getProperty() !== $this) {
            $address->setProperty($this);
        }
        $this->address = $address;

        return $this;
    }

    public function getRules(): ?PropertyRule
    {
        return $this->rules;
    }

    public function setRules(?PropertyRule $rules): static
    {
        if ($rules !== null && $rules->getProperty() !== $this) {
            $rules->setProperty($this);
        }
        $this->rules = $rules;

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
            $propertyAmenity->setProperty($this);
        }

        return $this;
    }

    public function removePropertyAmenity(PropertyAmenity $propertyAmenity): static
    {
        $this->propertyAmenities->removeElement($propertyAmenity);

        return $this;
    }

    /** @return Collection<int, PropertyMedia> */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedium(PropertyMedia $medium): static
    {
        if (!$this->media->contains($medium)) {
            $this->media->add($medium);
            $medium->setProperty($this);
        }

        return $this;
    }

    public function removeMedium(PropertyMedia $medium): static
    {
        $this->media->removeElement($medium);

        return $this;
    }

    /** @return Collection<int, PropertyAvailability> */
    public function getAvailabilities(): Collection
    {
        return $this->availabilities;
    }

    public function addAvailability(PropertyAvailability $availability): static
    {
        if (!$this->availabilities->contains($availability)) {
            $this->availabilities->add($availability);
            $availability->setProperty($this);
        }

        return $this;
    }

    public function removeAvailability(PropertyAvailability $availability): static
    {
        $this->availabilities->removeElement($availability);

        return $this;
    }

    /** @return Collection<int, AvailabilityBlock> */
    public function getAvailabilityBlocks(): Collection
    {
        return $this->availabilityBlocks;
    }

    public function addAvailabilityBlock(AvailabilityBlock $availabilityBlock): static
    {
        if (!$this->availabilityBlocks->contains($availabilityBlock)) {
            $this->availabilityBlocks->add($availabilityBlock);
            $availabilityBlock->setProperty($this);
        }

        return $this;
    }

    public function removeAvailabilityBlock(AvailabilityBlock $availabilityBlock): static
    {
        if ($this->availabilityBlocks->removeElement($availabilityBlock)) {
            if ($availabilityBlock->getProperty() === $this) {
                $availabilityBlock->setProperty(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, PropertyICalSync> */
    public function getICalSyncs(): Collection
    {
        return $this->iCalSyncs;
    }

    public function addICalSync(PropertyICalSync $iCalSync): static
    {
        if (!$this->iCalSyncs->contains($iCalSync)) {
            $this->iCalSyncs->add($iCalSync);
            $iCalSync->setProperty($this);
        }

        return $this;
    }

    public function removeICalSync(PropertyICalSync $iCalSync): static
    {
        $this->iCalSyncs->removeElement($iCalSync);

        return $this;
    }

    /** @return Collection<int, Reservation> */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setProperty($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        $this->reservations->removeElement($reservation);

        return $this;
    }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setProperty($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        $this->reviews->removeElement($review);

        return $this;
    }

    public function getCoverMedia(): ?PropertyMedia
    {
        foreach ($this->media as $medium) {
            if ($medium->isCover()) {
                return $medium;
            }
        }

        $sorted = $this->media->toArray();
        usort($sorted, static fn (PropertyMedia $a, PropertyMedia $b): int => $a->getSortOrder() <=> $b->getSortOrder());

        return $sorted[0] ?? null;
    }

    public function getAverageRating(): ?float
    {
        if ($this->reviews->isEmpty()) {
            return null;
        }

        $total = 0;
        foreach ($this->reviews as $review) {
            $total += $review->getRating() ?? 0;
        }

        return round($total / $this->reviews->count(), 2);
    }

    /** @return Collection<int, User> */
    public function getFavoritedBy(): Collection
    {
        return $this->favoritedBy;
    }

    public function addFavoritedBy(User $user): static
    {
        if (!$this->favoritedBy->contains($user)) {
            $this->favoritedBy->add($user);
            $user->addFavoriteProperty($this);
        }

        return $this;
    }

    public function removeFavoritedBy(User $user): static
    {
        if ($this->favoritedBy->removeElement($user)) {
            $user->removeFavoriteProperty($this);
        }

        return $this;
    }
}
