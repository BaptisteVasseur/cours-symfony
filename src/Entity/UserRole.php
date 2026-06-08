<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRoleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRoleRepository::class)]
#[ORM\Table(name: 'user_roles')]
class UserRole
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'userRoles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'userRoles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Role $role = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;

        return $this;
    }
}
