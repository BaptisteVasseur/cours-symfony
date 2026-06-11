<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\OwnerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: OwnerRepository::class)]
#[ORM\Table(name: 'owner')]
class Owner implements UserInterface, PasswordAuthenticatedUserInterface
{
    use UuidEntityTrait;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::STRING)]
    private string $password;

    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Property::class, orphanRemoval: true)]
    private Collection $properties;

    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: OwnerProfile::class, orphanRemoval: true)]
    private Collection $profiles;

    public function __construct()
    {
        $this->properties = new ArrayCollection();
        $this->profiles = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_OWNER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void {}

    public function getProperties(): Collection
    {
        return $this->properties;
    }

    public function addProperty(Property $property): self
    {
        if (!$this->properties->contains($property)) {
            $this->properties[] = $property;
            $property->setOwner($this);
        }

        return $this;
    }

    public function removeProperty(Property $property): self
    {
        if ($this->properties->removeElement($property)) {
            if ($property->getOwner() === $this) {
                $property->setOwner(null);
            }
        }

        return $this;
    }

    public function getProfiles(): Collection
    {
        return $this->profiles;
    }

    public function addProfile(OwnerProfile $profile): self
    {
        if (!$this->profiles->contains($profile)) {
            $this->profiles[] = $profile;
            $profile->setOwner($this);
        }

        return $this;
    }

    public function removeProfile(OwnerProfile $profile): self
    {
        if ($this->profiles->removeElement($profile)) {
            if ($profile->getOwner() === $this) {
                $profile->setOwner(null);
            }
        }

        return $this;
    }
}
