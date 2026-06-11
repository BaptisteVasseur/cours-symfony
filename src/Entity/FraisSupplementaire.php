<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FraisSupplementaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Logement::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Logement $logement;

    #[ORM\Column(length: 60)]
    public string $typeFrais = 'autre';

    #[ORM\Column(length: 120)]
    public string $nom = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $montant = '0.00';

    #[ORM\Column(length: 40)]
    public string $modeCalcul = 'fixe';

    #[ORM\Column]
    public bool $obligatoire = true;

    #[ORM\Column]
    public bool $actif = true;
}
