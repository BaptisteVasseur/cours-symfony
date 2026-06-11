<?php

namespace App\Entity;

use App\Enum\AvisStatut;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Avis
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
    public User $auteur;

    #[ORM\ManyToOne(targetEntity: User::class)]
    public ?User $cibleUtilisateur = null;

    #[ORM\ManyToOne(targetEntity: Logement::class)]
    public ?Logement $logement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $commentaire = null;

    #[ORM\Column]
    public int $noteGlobale = 5;

    #[ORM\Column(enumType: AvisStatut::class)]
    public AvisStatut $statut = AvisStatut::EN_ATTENTE;

    #[ORM\Column]
    public \DateTimeImmutable $dateCreation;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $datePublication = null;

    #[ORM\OneToOne(mappedBy: 'avis', targetEntity: NoteDetaillee::class, cascade: ['persist', 'remove'])]
    public ?NoteDetaillee $noteDetaillee = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }
}
