<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Recherche
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    public ?User $utilisateur = null;

    #[ORM\Column(length: 180)]
    public string $destination = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $dateArrivee = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $dateDepart = null;

    #[ORM\Column]
    public int $nombreVoyageurs = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    public ?string $prixMin = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    public ?string $prixMax = null;

    #[ORM\Column(type: Types::JSON)]
    public array $filtres = [];

    #[ORM\Column]
    public \DateTimeImmutable $dateRecherche;

    public function __construct()
    {
        $this->dateRecherche = new \DateTimeImmutable();
    }
}
