<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse email "{{ value }}" n\'est pas valide.')]
    #[Assert\Length(max: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $passwordHash = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $biography = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $preferredLanguage = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $preferredCurrency = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(choices: ['guest', 'host', 'admin'], message: 'Rôle invalide.')]
    private ?string $role = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $token = null;

    #[ORM\Column]
    private bool $emailVerified = false;

    #[ORM\Column]
    private bool $phoneVerified = false;

    #[ORM\Column]
    private bool $identityVerified = false;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'host', targetEntity: Listing::class)]
    private Collection $listings;

    #[ORM\OneToMany(mappedBy: 'guest', targetEntity: Booking::class)]
    private Collection $bookings;

    #[ORM\OneToMany(mappedBy: 'reviewer', targetEntity: Review::class)]
    private Collection $reviews;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class)]
    private Collection $notifications;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Wishlist::class)]
    private Collection $wishlists;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: RefreshToken::class)]
    private Collection $refreshTokens;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: AuthProvider::class)]
    private Collection $authProviders;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: UserIdentity::class)]
    private ?UserIdentity $identity = null;

    #[ORM\ManyToMany(mappedBy: 'participants', targetEntity: Conversation::class)]
    private Collection $conversations;

    public function __construct()
    {
        $this->listings = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->wishlists = new ArrayCollection();
        $this->refreshTokens = new ArrayCollection();
        $this->authProviders = new ArrayCollection();
        $this->conversations = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }

    /**
     * Identifiant de connexion utilisé par le firewall Symfony (provider: property "email").
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Rôles Symfony dérivés du champ métier `role`.
     * Tout utilisateur authentifié possède au minimum ROLE_USER.
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->role === 'admin') {
            $roles[] = 'ROLE_ADMIN';
        } elseif ($this->role === 'host') {
            $roles[] = 'ROLE_HOST';
        }

        return array_unique($roles);
    }

    /**
     * Mot de passe hashé stocké en base (interface PasswordAuthenticatedUserInterface).
     */
    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    /**
     * Aucune donnée sensible temporaire à effacer (mot de passe en clair non stocké).
     */
    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $v): static { $this->firstName = $v; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(string $v): static { $this->lastName = $v; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $v): static { $this->email = $v; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): static { $this->phone = $v; return $this; }

    public function getPasswordHash(): ?string { return $this->passwordHash; }
    public function setPasswordHash(string $v): static { $this->passwordHash = $v; return $this; }

    public function getProfilePicture(): ?string { return $this->profilePicture; }
    public function setProfilePicture(?string $v): static { $this->profilePicture = $v; return $this; }

    public function getBiography(): ?string { return $this->biography; }
    public function setBiography(?string $v): static { $this->biography = $v; return $this; }

    public function getPreferredLanguage(): ?string { return $this->preferredLanguage; }
    public function setPreferredLanguage(?string $v): static { $this->preferredLanguage = $v; return $this; }

    public function getPreferredCurrency(): ?string { return $this->preferredCurrency; }
    public function setPreferredCurrency(?string $v): static { $this->preferredCurrency = $v; return $this; }

    public function getRole(): ?string { return $this->role; }
    public function setRole(?string $v): static { $this->role = $v; return $this; }

    public function getToken(): ?string { return $this->token; }
    public function setToken(?string $v): static { $this->token = $v; return $this; }

    public function isEmailVerified(): bool { return $this->emailVerified; }
    public function setEmailVerified(bool $v): static { $this->emailVerified = $v; return $this; }

    public function isPhoneVerified(): bool { return $this->phoneVerified; }
    public function setPhoneVerified(bool $v): static { $this->phoneVerified = $v; return $this; }

    public function isIdentityVerified(): bool { return $this->identityVerified; }
    public function setIdentityVerified(bool $v): static { $this->identityVerified = $v; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(?string $v): static { $this->status = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $v): static { $this->updatedAt = $v; return $this; }

    /** @return Collection<int, Listing> */
    public function getListings(): Collection { return $this->listings; }

    /** @return Collection<int, Booking> */
    public function getBookings(): Collection { return $this->bookings; }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection { return $this->reviews; }

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection { return $this->notifications; }

    /** @return Collection<int, Wishlist> */
    public function getWishlists(): Collection { return $this->wishlists; }

    /** @return Collection<int, RefreshToken> */
    public function getRefreshTokens(): Collection { return $this->refreshTokens; }

    /** @return Collection<int, AuthProvider> */
    public function getAuthProviders(): Collection { return $this->authProviders; }

    public function getIdentity(): ?UserIdentity { return $this->identity; }

    /** @return Collection<int, Conversation> */
    public function getConversations(): Collection { return $this->conversations; }
}
