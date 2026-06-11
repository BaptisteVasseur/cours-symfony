<?php

namespace App\Entity;

use App\Enum\LitigeStatut;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Litige
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
    public User $voyageur;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $hote;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $ouvertPar;

    #[ORM\Column(length: 80)]
    public string $motif = 'autre';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(enumType: LitigeStatut::class)]
    public LitigeStatut $statut = LitigeStatut::OUVERT;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $decision = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    public ?string $montantCompensation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    public ?User $administrateur = null;

    #[ORM\Column]
    public \DateTimeImmutable $dateOuverture;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateCloture = null;

    public function __construct()
    {
        $this->dateOuverture = new \DateTimeImmutable();
    }
}
