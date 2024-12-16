<?php

namespace App\Entity;

use App\Repository\SubscriptionHistoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SubscriptionHistoryRepository::class)]
class SubscriptionHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['groupA'])]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column]
    #[Groups(['groupA'])]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\ManyToOne(inversedBy: 'subscriptionHistories')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $subscriber = null;

    #[Groups(['groupA'])]
    #[ORM\ManyToOne(inversedBy: 'subscriptionHistories')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Subscription $subscription = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getSubscriber(): ?User
    {
        return $this->subscriber;
    }

    public function setSubscriber(?User $subscriber): static
    {
        $this->subscriber = $subscriber;

        return $this;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): static
    {
        $this->subscription = $subscription;

        return $this;
    }
}
