<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\OauthAccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OauthAccountRepository::class)]
#[ORM\Table(name: 'oauth_accounts')]
class OauthAccount
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'oauthAccounts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[Assert\NotBlank(message: 'Le fournisseur OAuth est obligatoire.')]
    #[Assert\Choice(
        choices: ['google', 'facebook', 'apple', 'github', 'linkedin'],
        message: 'Le fournisseur OAuth sélectionné n\'est pas valide.',
    )]
    #[ORM\Column(length: 50)]
    private ?string $provider = null;

    #[Assert\NotBlank(message: 'L\'identifiant fournisseur est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'L\'identifiant ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 255)]
    private ?string $providerUserId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $accessToken = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $refreshToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderUserId(): ?string
    {
        return $this->providerUserId;
    }

    public function setProviderUserId(string $providerUserId): static
    {
        $this->providerUserId = $providerUserId;

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;

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
}
