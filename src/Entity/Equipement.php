<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Equipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    public string $nom = '';

    #[ORM\Column(length: 120)]
    public string $categorie = '';

    #[ORM\Column(length: 80, nullable: true)]
    public ?string $icone = null;

    #[ORM\Column]
    public bool $actif = true;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $description = null;
}
