<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\PaymentMethodRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentMethodRepository::class)]
#[ORM\Table(name: 'payment_methods')]
class PaymentMethod
{
    use UuidEntityTrait;

    #[Assert\NotNull]
    #[ORM\ManyToOne(inversedBy: 'paymentMethods')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['stripe', 'paypal'])]
    #[ORM\Column(length: 50)]
    private ?string $provider = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255)]
    private ?string $providerPaymentMethodId = null;

    #[Assert\Choice(choices: ['visa', 'mastercard', 'amex', 'cb'])]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $brand = null;

    #[Assert\Length(exactly: 4)]
    #[Assert\Regex(pattern: '/^\d{4}$/')]
    #[ORM\Column(length: 4, nullable: true)]
    private ?string $last4 = null;

    #[Assert\Range(min: 1, max: 12)]
    #[ORM\Column(nullable: true)]
    private ?int $expirationMonth = null;

    #[Assert\Range(min: 2024, max: 2040)]
    #[ORM\Column(nullable: true)]
    private ?int $expirationYear = null;

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

    public function getProviderPaymentMethodId(): ?string
    {
        return $this->providerPaymentMethodId;
    }

    public function setProviderPaymentMethodId(string $providerPaymentMethodId): static
    {
        $this->providerPaymentMethodId = $providerPaymentMethodId;

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getLast4(): ?string
    {
        return $this->last4;
    }

    public function setLast4(?string $last4): static
    {
        $this->last4 = $last4;

        return $this;
    }

    public function getExpirationMonth(): ?int
    {
        return $this->expirationMonth;
    }

    public function setExpirationMonth(?int $expirationMonth): static
    {
        $this->expirationMonth = $expirationMonth;

        return $this;
    }

    public function getExpirationYear(): ?int
    {
        return $this->expirationYear;
    }

    public function setExpirationYear(?int $expirationYear): static
    {
        $this->expirationYear = $expirationYear;

        return $this;
    }
}
