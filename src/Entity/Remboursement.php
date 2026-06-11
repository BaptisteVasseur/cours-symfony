<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Remboursement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Paiement::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Paiement $paiement;

    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Reservation $reservation;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $montant = '0.00';

    #[ORM\ManyToOne(targetEntity: Devise::class)]
    #[ORM\JoinColumn(referencedColumnName: 'code')]
    public ?Devise $devise = null;

    #[ORM\Column(length: 20)]
    public string $type = 'total';

    #[ORM\Column(length: 80)]
    public string $motif = 'annulation_voyageur';

    #[ORM\Column(length: 30)]
    public string $statut = 'en_attente';

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $referencePrestataire = null;

    #[ORM\Column]
    public \DateTimeImmutable $dateDemande;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateTraitement = null;

    public function __construct()
    {
        $this->dateDemande = new \DateTimeImmutable();
    }
}
