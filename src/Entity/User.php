<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $passwordHash = null;

    #[ORM\Column(length: 20)]
    private ?string $role = 'guest';

    #[ORM\Column]
    private ?bool $isEmailVerified = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserProfile $profile = null;

    /** @var Collection<int, Listing> */
    #[ORM\OneToMany(targetEntity: Listing::class, mappedBy: 'host')]
    private Collection $listings;

    /** @var Collection<int, Booking> */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'guest')]
    private Collection $bookings;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $notifications;

    /** @var Collection<int, AdminAction> */
    #[ORM\OneToMany(targetEntity: AdminAction::class, mappedBy: 'admin')]
    private Collection $adminActions;

    /** @var Collection<int, Conversation> */
    #[ORM\ManyToMany(targetEntity: Conversation::class, mappedBy: 'participants')]
    private Collection $conversations;

    public function __construct()
    {
        $this->listings = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->adminActions = new ArrayCollection();
        $this->conversations = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getPassword(): ?string { return $this->passwordHash; }

    public function eraseCredentials(): void {}

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getPasswordHash(): ?string { return $this->passwordHash; }
    public function setPasswordHash(string $passwordHash): static { $this->passwordHash = $passwordHash; return $this; }

    public function getRole(): ?string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }

    public function isEmailVerified(): ?bool { return $this->isEmailVerified; }
    public function setIsEmailVerified(bool $isEmailVerified): static { $this->isEmailVerified = $isEmailVerified; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getProfile(): ?UserProfile { return $this->profile; }
    public function setProfile(?UserProfile $profile): static
    {
        if ($profile === null && $this->profile !== null) {
            $this->profile->setUser(null);
        }
        if ($profile !== null && $profile->getUser() !== $this) {
            $profile->setUser($this);
        }
        $this->profile = $profile;
        return $this;
    }

    /** @return Collection<int, Listing> */
    public function getListings(): Collection { return $this->listings; }

    public function addListing(Listing $listing): static
    {
        if (!$this->listings->contains($listing)) {
            $this->listings->add($listing);
            $listing->setHost($this);
        }
        return $this;
    }

    public function removeListing(Listing $listing): static
    {
        if ($this->listings->removeElement($listing) && $listing->getHost() === $this) {
            $listing->setHost(null);
        }
        return $this;
    }

    /** @return Collection<int, Booking> */
    public function getBookings(): Collection { return $this->bookings; }

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
        if ($this->bookings->removeElement($booking) && $booking->getGuest() === $this) {
            $booking->setGuest(null);
        }
        return $this;
    }

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection { return $this->notifications; }

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
        if ($this->notifications->removeElement($notification) && $notification->getUser() === $this) {
            $notification->setUser(null);
        }
        return $this;
    }

    /** @return Collection<int, AdminAction> */
    public function getAdminActions(): Collection { return $this->adminActions; }

    public function addAdminAction(AdminAction $adminAction): static
    {
        if (!$this->adminActions->contains($adminAction)) {
            $this->adminActions->add($adminAction);
            $adminAction->setAdmin($this);
        }
        return $this;
    }

    public function removeAdminAction(AdminAction $adminAction): static
    {
        if ($this->adminActions->removeElement($adminAction) && $adminAction->getAdmin() === $this) {
            $adminAction->setAdmin(null);
        }
        return $this;
    }

    /** @return Collection<int, Conversation> */
    public function getConversations(): Collection { return $this->conversations; }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        return array_unique(['ROLE_' . strtoupper($this->role ?? 'USER'), 'ROLE_USER']);
    }
}
