<?php

namespace App\Entity;

use App\Enum\ReservationStatut;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Logement::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Logement $logement;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reservationsVoyageur')]
    #[ORM\JoinColumn(nullable: false)]
    public User $voyageur;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $hote;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public \DateTimeImmutable $dateArrivee;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public \DateTimeImmutable $dateDepart;

    #[ORM\Column]
    public int $nombreNuits = 1;

    #[ORM\Column]
    public int $nombreVoyageurs = 1;

    #[ORM\Column(enumType: ReservationStatut::class)]
    public ReservationStatut $statut = ReservationStatut::BROUILLON;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $prixNuits = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $fraisMenage = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $fraisService = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $taxeSejour = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $montantTotal = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $montantHote = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $commissionPlateforme = '0.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $messageVoyageur = null;

    #[ORM\Column]
    public \DateTimeImmutable $dateDemande;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateAcceptation = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateExpirationPaiement = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateConfirmation = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateAnnulation = null;

    public function __construct()
    {
        $this->dateArrivee = new \DateTimeImmutable();
        $this->dateDepart = new \DateTimeImmutable('+1 day');
        $this->dateDemande = new \DateTimeImmutable();
    }
}
