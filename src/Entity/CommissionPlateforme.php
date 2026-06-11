<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CommissionPlateforme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\OneToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Reservation $reservation;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    public string $tauxCommissionHote = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    public string $tauxFraisVoyageur = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $montantCommissionHote = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $montantFraisVoyageur = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $montantTotalPlateforme = '0.00';

    #[ORM\ManyToOne(targetEntity: Devise::class)]
    #[ORM\JoinColumn(referencedColumnName: 'code')]
    public ?Devise $devise = null;

    #[ORM\Column]
    public \DateTimeImmutable $dateCalcul;

    public function __construct()
    {
        $this->dateCalcul = new \DateTimeImmutable();
    }
}
