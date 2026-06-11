<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Promotion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Logement::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Logement $logement;

    #[ORM\Column(length: 120)]
    public string $nom = '';

    #[ORM\Column(length: 40)]
    public string $type = 'pourcentage';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $valeur = '0.00';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public \DateTimeImmutable $dateDebut;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public \DateTimeImmutable $dateFin;

    #[ORM\Column(type: Types::JSON)]
    public array $conditions = [];

    #[ORM\Column]
    public bool $actif = true;

    public function __construct()
    {
        $this->dateDebut = new \DateTimeImmutable();
        $this->dateFin = new \DateTimeImmutable('+1 month');
    }
}
