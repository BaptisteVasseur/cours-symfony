<?php

namespace App\Entity;

use App\Repository\GamificationUserStatsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GamificationUserStatsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class GamificationUserStats
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'gamificationStats')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $totalPoints = 0;

    #[ORM\Column(options: ['default' => 1])]
    private ?int $level = 1;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $bookingsCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $reviewsCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $countriesVisited = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTotalPoints(): ?int
    {
        return $this->totalPoints;
    }

    public function setTotalPoints(int $totalPoints): static
    {
        $this->totalPoints = $totalPoints;
        return $this;
    }

    public function addPoints(int $points): static
    {
        $this->totalPoints += $points;
        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getBookingsCount(): ?int
    {
        return $this->bookingsCount;
    }

    public function setBookingsCount(int $bookingsCount): static
    {
        $this->bookingsCount = $bookingsCount;
        return $this;
    }

    public function incrementBookingsCount(): static
    {
        $this->bookingsCount++;
        return $this;
    }

    public function getReviewsCount(): ?int
    {
        return $this->reviewsCount;
    }

    public function setReviewsCount(int $reviewsCount): static
    {
        $this->reviewsCount = $reviewsCount;
        return $this;
    }

    public function incrementReviewsCount(): static
    {
        $this->reviewsCount++;
        return $this;
    }

    public function getCountriesVisited(): ?int
    {
        return $this->countriesVisited;
    }

    public function setCountriesVisited(int $countriesVisited): static
    {
        $this->countriesVisited = $countriesVisited;
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
}
