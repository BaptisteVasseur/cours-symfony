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
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN') or object == user"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_SUPER_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['mon-groupe']],
    denormalizationContext: ['groups' => ['mon-groupe-2']],
)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use UuidEntityTrait;

    #[Groups(['mon-groupe'])]
    #[Assert\NotBlank(message: 'L\'adresse email est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse email n\'est pas valide.')]
    #[Assert\Length(max: 255, maxMessage: 'L\'adresse email ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.', groups: ['create'])]
    #[ORM\Column(length: 255)]
    private ?string $passwordHash = null;

    #[Groups(['mon-groupe'])]
    #[Assert\Length(max: 50, maxMessage: 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères.')]
    #[Assert\Regex(pattern: '/^\+?[0-9\s\-().]{6,20}$/', message: 'Le numéro de téléphone n\'est pas valide.')]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $hostIcalToken = null;

    #[Assert\NotBlank(message: 'Le statut du compte est obligatoire.')]
    #[Assert\Choice(
        choices: ['active', 'pending', 'suspended'],
        message: 'Le statut sélectionné n\'est pas valide.',
    )]
    #[ORM\Column(length: 50)]
    private ?string $status = 'pending';

    #[ORM\Column]
    private bool $isEmailVerified = false;

    #[ORM\Column]
    private bool $is2faEnabled = false;

    #[Assert\Choice(
        choices: ['fr', 'en', 'es'],
        message: 'La langue sélectionnée n\'est pas valide.',
    )]
    #[Assert\Length(max: 10, maxMessage: 'La langue ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $preferredLanguage = null;

    #[Assert\Choice(
        choices: ['EUR', 'USD', 'GBP'],
        message: 'La devise sélectionnée n\'est pas valide.',
    )]
    #[Assert\Length(max: 10, maxMessage: 'La devise ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $preferredCurrency = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[Groups(['mon-groupe'])]
    #[Assert\Valid]
    #[ORM\OneToOne(mappedBy: 'user', targetEntity: UserProfile::class, cascade: ['persist', 'remove'])]
    private ?UserProfile $profile = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    /** @var Collection<int, OauthAccount> */
    #[ORM\OneToMany(targetEntity: OauthAccount::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $oauthAccounts;

    /** @var Collection<int, UserDocument> */
    #[ORM\OneToMany(targetEntity: UserDocument::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $documents;

    /** @var Collection<int, Property> */
    #[ORM\OneToMany(targetEntity: Property::class, mappedBy: 'host')]
    private Collection $properties;

    /** @var Collection<int, Reservation> */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'guest')]
    private Collection $reservations;

    /** @var Collection<int, PaymentMethod> */
    #[ORM\OneToMany(targetEntity: PaymentMethod::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $paymentMethods;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $notifications;

    /** @var Collection<int, AuditLog> */
    #[ORM\OneToMany(targetEntity: AuditLog::class, mappedBy: 'user')]
    private Collection $auditLogs;

    public function __construct()
    {
        $this->oauthAccounts = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->properties = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->paymentMethods = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->auditLogs = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @return list<string> */
    public function getAssignedRoles(): array
    {
        return $this->roles;
    }

    /** @param list<string> $roles */
    public function setAssignedRoles(array $roles): static
    {
        $this->roles = array_values(array_unique(array_filter(
            $roles,
            static fn (string $role): bool => $role !== 'ROLE_USER',
        )));

        return $this;
    }

    public function addAssignedRole(string $role): static
    {
        if ($role !== 'ROLE_USER' && !in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function removeAssignedRole(string $role): static
    {
        $this->roles = array_values(array_filter(
            $this->roles,
            static fn (string $assignedRole): bool => $assignedRole !== $role,
        ));

        return $this;
    }

    public function hasAssignedRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function eraseCredentials(): void
    {
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

    #[Ignore]
    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    #[Ignore]
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

    public function getHostIcalToken(): ?string
    {
        return $this->hostIcalToken;
    }

    public function setHostIcalToken(?string $hostIcalToken): static
    {
        $this->hostIcalToken = $hostIcalToken;

        return $this;
    }

    public function generateHostIcalToken(): string
    {
        $this->hostIcalToken = bin2hex(random_bytes(32));

        return $this->hostIcalToken;
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

    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    public function setIsEmailVerified(bool $isEmailVerified): static
    {
        $this->isEmailVerified = $isEmailVerified;

        return $this;
    }

    public function is2faEnabled(): bool
    {
        return $this->is2faEnabled;
    }

    public function setIs2faEnabled(bool $is2faEnabled): static
    {
        $this->is2faEnabled = $is2faEnabled;

        return $this;
    }

    public function getPreferredLanguage(): ?string
    {
        return $this->preferredLanguage;
    }

    public function setPreferredLanguage(?string $preferredLanguage): static
    {
        $this->preferredLanguage = $preferredLanguage;

        return $this;
    }

    public function getPreferredCurrency(): ?string
    {
        return $this->preferredCurrency;
    }

    public function setPreferredCurrency(?string $preferredCurrency): static
    {
        $this->preferredCurrency = $preferredCurrency;

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

    public function getProfile(): ?UserProfile
    {
        return $this->profile;
    }

    public function setProfile(?UserProfile $profile): static
    {
        if ($profile !== null && $profile->getUser() !== $this) {
            $profile->setUser($this);
        }
        $this->profile = $profile;

        return $this;
    }

    /** @return Collection<int, OauthAccount> */
    public function getOauthAccounts(): Collection
    {
        return $this->oauthAccounts;
    }

    public function addOauthAccount(OauthAccount $oauthAccount): static
    {
        if (!$this->oauthAccounts->contains($oauthAccount)) {
            $this->oauthAccounts->add($oauthAccount);
            $oauthAccount->setUser($this);
        }

        return $this;
    }

    public function removeOauthAccount(OauthAccount $oauthAccount): static
    {
        $this->oauthAccounts->removeElement($oauthAccount);

        return $this;
    }

    /** @return Collection<int, UserDocument> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(UserDocument $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setUser($this);
        }

        return $this;
    }

    public function removeDocument(UserDocument $document): static
    {
        $this->documents->removeElement($document);

        return $this;
    }

    /** @return Collection<int, Property> */
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
        $this->properties->removeElement($property);

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
            $reservation->setGuest($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        $this->reservations->removeElement($reservation);

        return $this;
    }

    /** @return Collection<int, PaymentMethod> */
    public function getPaymentMethods(): Collection
    {
        return $this->paymentMethods;
    }

    public function addPaymentMethod(PaymentMethod $paymentMethod): static
    {
        if (!$this->paymentMethods->contains($paymentMethod)) {
            $this->paymentMethods->add($paymentMethod);
            $paymentMethod->setUser($this);
        }

        return $this;
    }

    public function removePaymentMethod(PaymentMethod $paymentMethod): static
    {
        $this->paymentMethods->removeElement($paymentMethod);

        return $this;
    }

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        $this->notifications->removeElement($notification);

        return $this;
    }

    /** @return Collection<int, AuditLog> */
    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    public function addAuditLog(AuditLog $auditLog): static
    {
        if (!$this->auditLogs->contains($auditLog)) {
            $this->auditLogs->add($auditLog);
            $auditLog->setUser($this);
        }

        return $this;
    }

    public function removeAuditLog(AuditLog $auditLog): static
    {
        $this->auditLogs->removeElement($auditLog);

        return $this;
    }
}
