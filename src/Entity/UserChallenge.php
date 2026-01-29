<?php

namespace App\Entity;

use App\Repository\UserChallengeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserChallengeRepository::class)]
class UserChallenge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userChallenges')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userChallenges')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Challenge $challenge = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $currentProgress = 0;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $isCompleted = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startedAt = null;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
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

    public function getChallenge(): ?Challenge
    {
        return $this->challenge;
    }

    public function setChallenge(?Challenge $challenge): static
    {
        $this->challenge = $challenge;
        return $this;
    }

    public function getCurrentProgress(): ?int
    {
        return $this->currentProgress;
    }

    public function setCurrentProgress(int $currentProgress): static
    {
        $this->currentProgress = $currentProgress;
        return $this;
    }

    public function incrementProgress(int $amount = 1): static
    {
        $this->currentProgress += $amount;
        return $this;
    }

    public function isCompleted(): ?bool
    {
        return $this->isCompleted;
    }

    public function setIsCompleted(bool $isCompleted): static
    {
        $this->isCompleted = $isCompleted;
        if ($isCompleted && $this->completedAt === null) {
            $this->completedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getProgressPercentage(): float
    {
        if ($this->challenge === null || $this->challenge->getTargetValue() === 0) {
            return 0;
        }
        return min(100, ($this->currentProgress / $this->challenge->getTargetValue()) * 100);
    }
}
