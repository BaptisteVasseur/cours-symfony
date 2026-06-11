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

    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'paymentMethods')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[Assert\NotBlank(message: 'Le prestataire est obligatoire.')]
    #[Assert\Choice(
        choices: ['stripe', 'paypal'],
        message: 'Le prestataire sélectionné n\'est pas valide.',
    )]
    #[ORM\Column(length: 50)]
    private ?string $provider = null;

    #[Assert\NotBlank(message: 'L\'identifiant de moyen de paiement est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'L\'identifiant ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 255)]
    private ?string $providerPaymentMethodId = null;

    #[Assert\Length(max: 50, maxMessage: 'La marque ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $brand = null;

    #[Assert\Length(exactly: 4, exactMessage: 'Les 4 derniers chiffres doivent comporter exactement {{ limit }} caractères.')]
    #[Assert\Regex(pattern: '/^\d{4}$/', message: 'Les 4 derniers chiffres doivent contenir uniquement des chiffres.')]
    #[ORM\Column(length: 4, nullable: true)]
    private ?string $last4 = null;

    #[Assert\Range(
        notInRangeMessage: 'Le mois d\'expiration doit être compris entre {{ min }} et {{ max }}.',
        min: 1,
        max: 12,
    )]
    #[ORM\Column(nullable: true)]
    private ?int $expirationMonth = null;

    #[Assert\Range(
        notInRangeMessage: 'L\'année d\'expiration doit être comprise entre {{ min }} et {{ max }}.',
        min: 2000,
        max: 2100,
    )]
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
