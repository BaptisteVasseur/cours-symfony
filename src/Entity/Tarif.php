<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Tarif
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'tarif', targetEntity: Logement::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Logement $logement;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $prixNuit = '0.00';

    #[ORM\ManyToOne(targetEntity: Devise::class)]
    #[ORM\JoinColumn(referencedColumnName: 'code')]
    public ?Devise $devise = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $fraisMenage = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $fraisVoyageur = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $fraisHote = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $taxeSejour = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $depotGarantie = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    public string $reductionSemaine = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    public string $reductionMois = '0.00';

    #[ORM\Column]
    public bool $actif = true;
}
