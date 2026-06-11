<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Adresse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'adresse', targetEntity: Logement::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Logement $logement;

    #[ORM\Column(length: 255)]
    public string $adresseLigne1 = '';

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $adresseLigne2 = null;

    #[ORM\Column(length: 120)]
    public string $ville = '';

    #[ORM\Column(length: 20)]
    public string $codePostal = '';

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $region = null;

    #[ORM\Column(length: 120)]
    public string $pays = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    public ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    public ?string $longitude = null;

    #[ORM\Column]
    public bool $affichageApproximatif = true;
}
