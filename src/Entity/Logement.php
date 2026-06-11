<?php

namespace App\Entity;

use App\Enum\LogementCategorie;
use App\Enum\LogementStatut;
use App\Enum\LogementType;
use App\Repository\LogementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LogementRepository::class)]
class Logement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'logements')]
    #[ORM\JoinColumn(nullable: false)]
    public User $hote;

    #[ORM\Column(length: 180)]
    public string $titre = '';

    #[ORM\Column(type: Types::TEXT)]
    public string $description = '';

    #[ORM\Column(enumType: LogementType::class)]
    public LogementType $typeLogement = LogementType::APPARTEMENT;

    #[ORM\Column(enumType: LogementCategorie::class)]
    public LogementCategorie $categorie = LogementCategorie::LOGEMENT_ENTIER;

    #[ORM\Column]
    public int $capaciteVoyageurs = 1;

    #[ORM\Column]
    public int $nombreChambres = 1;

    #[ORM\Column]
    public int $nombreLits = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 1)]
    public string $nombreSallesBain = '1.0';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    public ?string $surface = null;

    #[ORM\Column(enumType: LogementStatut::class)]
    public LogementStatut $statut = LogementStatut::BROUILLON;

    #[ORM\Column]
    public bool $instantBooking = false;

    #[ORM\Column(length: 64, unique: true)]
    public string $icalToken = '';

    #[ORM\ManyToOne(targetEntity: PolitiqueAnnulation::class)]
    public ?PolitiqueAnnulation $politiqueAnnulation = null;

    #[ORM\OneToOne(mappedBy: 'logement', targetEntity: Adresse::class, cascade: ['persist', 'remove'])]
    public ?Adresse $adresse = null;

    #[ORM\OneToOne(mappedBy: 'logement', targetEntity: ReglementInterieur::class, cascade: ['persist', 'remove'])]
    public ?ReglementInterieur $reglementInterieur = null;

    #[ORM\OneToOne(mappedBy: 'logement', targetEntity: Tarif::class, cascade: ['persist', 'remove'])]
    public ?Tarif $tarif = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2)]
    public string $noteMoyenne = '0.00';

    #[ORM\Column]
    public int $nombreAvis = 0;

    #[ORM\Column]
    public \DateTimeImmutable $dateCreation;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $datePublication = null;

    #[ORM\Column]
    public \DateTimeImmutable $dateMiseAJour;

    /** @var Collection<int, PhotoLogement> */
    #[ORM\OneToMany(mappedBy: 'logement', targetEntity: PhotoLogement::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $photos;

    /** @var Collection<int, Equipement> */
    #[ORM\ManyToMany(targetEntity: Equipement::class)]
    public Collection $equipements;

    /** @var Collection<int, Disponibilite> */
    #[ORM\OneToMany(mappedBy: 'logement', targetEntity: Disponibilite::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $disponibilites;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
        $this->dateMiseAJour = new \DateTimeImmutable();
        $this->icalToken = $this->genererTokenIcal();
        $this->photos = new ArrayCollection();
        $this->equipements = new ArrayCollection();
        $this->disponibilites = new ArrayCollection();
    }

    public function regenererTokenIcal(): void
    {
        $this->icalToken = $this->genererTokenIcal();
        $this->dateMiseAJour = new \DateTimeImmutable();
    }

    private function genererTokenIcal(): string
    {
        return bin2hex(random_bytes(32));
    }
}
