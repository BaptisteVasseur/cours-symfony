<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PolitiqueAnnulation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 120)]
    public string $nom = '';

    #[ORM\Column(type: Types::TEXT)]
    public string $description = '';

    #[ORM\Column]
    public int $delaiRemboursementTotal = 0;

    #[ORM\Column]
    public int $delaiRemboursementPartiel = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    public string $pourcentageRemboursementPartiel = '0.00';

    #[ORM\Column]
    public bool $fraisServiceRemboursables = false;

    #[ORM\Column]
    public bool $actif = true;
}
