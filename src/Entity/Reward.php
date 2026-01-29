<?php

namespace App\Entity;

use App\Repository\RewardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RewardRepository::class)]
class Reward
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $rewardType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $discountPercentage = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $discountAmount = null;

    #[ORM\Column]
    private ?int $pointsRequired = null;

    #[ORM\Column(options: ['default' => true])]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: UserReward::class, mappedBy: 'reward', orphanRemoval: true)]
    private Collection $userRewards;

    public function __construct()
    {
        $this->userRewards = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getRewardType(): ?string
    {
        return $this->rewardType;
    }

    public function setRewardType(string $rewardType): static
    {
        $this->rewardType = $rewardType;
        return $this;
    }

    public function getDiscountPercentage(): ?string
    {
        return $this->discountPercentage;
    }

    public function setDiscountPercentage(?string $discountPercentage): static
    {
        $this->discountPercentage = $discountPercentage;
        return $this;
    }

    public function getDiscountAmount(): ?string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(?string $discountAmount): static
    {
        $this->discountAmount = $discountAmount;
        return $this;
    }

    public function getPointsRequired(): ?int
    {
        return $this->pointsRequired;
    }

    public function setPointsRequired(int $pointsRequired): static
    {
        $this->pointsRequired = $pointsRequired;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    public function getUserRewards(): Collection
    {
        return $this->userRewards;
    }
}
