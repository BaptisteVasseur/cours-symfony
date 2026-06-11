<?php

namespace App\Entity;

use App\Enum\PaiementStatut;
use App\Repository\PaiementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Reservation $reservation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $utilisateur;

    #[ORM\Column(length: 20)]
    public string $prestataire = 'stripe';

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $referencePrestataire = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $montant = '0.00';

    #[ORM\ManyToOne(targetEntity: Devise::class)]
    #[ORM\JoinColumn(referencedColumnName: 'code')]
    public ?Devise $devise = null;

    #[ORM\Column(enumType: PaiementStatut::class)]
    public PaiementStatut $statut = PaiementStatut::EN_ATTENTE;

    #[ORM\Column]
    public \DateTimeImmutable $dateCreation;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $datePaiement = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateEchec = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $motifEchec = null;

    #[ORM\Column(type: Types::JSON)]
    public array $metadata = [];

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }
}
