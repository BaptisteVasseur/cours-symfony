<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new GetCollection(),
        new Patch(
            normalizationContext: ['groups' => ['groupe-supprimer-user']]
        ),
    ],
    normalizationContext: ['groups' => ['groupeA']],
    denormalizationContext: ['groups' => ['creer-user']],
    security: 'is_granted("ROLE_ADMIN")'
)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['groupeA', 'creer-user'])]
    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $passwordHash = null;

    #[Groups(['groupeA', 'creer-user'])]
    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[Groups(['groupeA', 'creer-user'])]
    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[Groups(['creer-user'])]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $profilePictureUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 10, options: ['default' => 'fr'])]
    private ?string $language = 'fr';

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private ?string $currency = 'EUR';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[Groups(['groupeA'])]
    #[ORM\OneToMany(targetEntity: Property::class, mappedBy: 'host')]
    private Collection $properties;

    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'guest', orphanRemoval: true)]
    private Collection $bookings;

    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'reviewer')]
    private Collection $reviewsWritten;

    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'reviewee')]
    private Collection $reviewsReceived;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'sender')]
    private Collection $messagesSent;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'recipient')]
    private Collection $messagesReceived;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?GamificationUserStats $gamificationStats = null;

    #[ORM\OneToMany(targetEntity: UserBadge::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userBadges;

    #[ORM\OneToMany(targetEntity: UserChallenge::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userChallenges;

    #[ORM\OneToMany(targetEntity: UserReward::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userRewards;

    /** @var string|null waiting, active, suspended, deleted */
    #[ORM\Column(length: 255)]
    private ?string $state = 'waiting';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $activationToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resetPasswordToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resetPasswordAt = null;

    public function __construct()
    {
        $this->properties = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->reviewsWritten = new ArrayCollection();
        $this->reviewsReceived = new ArrayCollection();
        $this->messagesSent = new ArrayCollection();
        $this->messagesReceived = new ArrayCollection();
        $this->userBadges = new ArrayCollection();
        $this->userChallenges = new ArrayCollection();
        $this->userRewards = new ArrayCollection();

        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getProfilePictureUrl(): ?string
    {
        return $this->profilePictureUrl;
    }

    public function setProfilePictureUrl(?string $profilePictureUrl): static
    {
        $this->profilePictureUrl = $profilePictureUrl;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    public function getProperties(): Collection
    {
        return $this->properties;
    }

    public function addProperty(Property $property): static
    {
        if (!$this->properties->contains($property)) {
            $this->properties->add($property);
            $property->setHost($this);
        }
        return $this;
    }

    public function removeProperty(Property $property): static
    {
        if ($this->properties->removeElement($property)) {
            if ($property->getHost() === $this) {
                $property->setHost(null);
            }
        }
        return $this;
    }

    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setGuest($this);
        }
        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getGuest() === $this) {
                $booking->setGuest(null);
            }
        }
        return $this;
    }

    public function getReviewsWritten(): Collection
    {
        return $this->reviewsWritten;
    }

    public function getReviewsReceived(): Collection
    {
        return $this->reviewsReceived;
    }

    public function getMessagesSent(): Collection
    {
        return $this->messagesSent;
    }

    public function getMessagesReceived(): Collection
    {
        return $this->messagesReceived;
    }

    public function getGamificationStats(): ?GamificationUserStats
    {
        return $this->gamificationStats;
    }

    public function setGamificationStats(?GamificationUserStats $gamificationStats): static
    {
        if ($gamificationStats !== null && $gamificationStats->getUser() !== $this) {
            $gamificationStats->setUser($this);
        }
        $this->gamificationStats = $gamificationStats;
        return $this;
    }

    public function getUserBadges(): Collection
    {
        return $this->userBadges;
    }

    public function getUserChallenges(): Collection
    {
        return $this->userChallenges;
    }

    public function getUserRewards(): Collection
    {
        return $this->userRewards;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getActivationToken(): ?string
    {
        return $this->activationToken;
    }

    public function setActivationToken(?string $activationToken): static
    {
        $this->activationToken = $activationToken;

        return $this;
    }

    public function getResetPasswordToken(): ?string
    {
        return $this->resetPasswordToken;
    }

    public function setResetPasswordToken(?string $resetPasswordToken): static
    {
        $this->resetPasswordToken = $resetPasswordToken;

        return $this;
    }

    public function getResetPasswordAt(): ?\DateTimeImmutable
    {
        return $this->resetPasswordAt;
    }

    public function setResetPasswordAt(?\DateTimeImmutable $resetPasswordAt): static
    {
        $this->resetPasswordAt = $resetPasswordAt;

        return $this;
    }
}
