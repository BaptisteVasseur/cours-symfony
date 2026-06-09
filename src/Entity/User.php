<?php

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'text')]
    private ?string $passwordHash = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(enumType: UserRole::class)]
    private UserRole $role = UserRole::TRAVELER;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $profilePictureUrl = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: Property::class, mappedBy: 'host', cascade: ['persist'])]
    private Collection $properties;

    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'traveler', cascade: ['persist'])]
    private Collection $bookings;

    #[ORM\OneToOne(targetEntity: UserPreference::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserPreference $preference = null;

    public function __construct()
    {
        $this->properties = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): static
    {
        $this->role = $role;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getPreference(): ?UserPreference
    {
        return $this->preference;
    }

    public function setPreference(?UserPreference $preference): static
    {
        $this->preference = $preference;
        return $this;
    }

    /** @return Collection<int, Property> */
    public function getProperties(): Collection
    {
        return $this->properties;
    }

    /** @return Collection<int, Booking> */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        return match ($this->role) {
            UserRole::ADMIN => ['ROLE_ADMIN', 'ROLE_USER'],
            UserRole::HOST => ['ROLE_HOST', 'ROLE_USER'],
            default => ['ROLE_USER'],
        };
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function eraseCredentials(): void {}
}
