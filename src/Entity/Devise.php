<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Devise
{
    #[ORM\Id]
    #[ORM\Column(length: 3)]
    public string $code = 'EUR';

    #[ORM\Column(length: 100)]
    public string $nom = '';

    #[ORM\Column(length: 10)]
    public string $symbole = '';

    #[ORM\Column]
    public bool $actif = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    public ?string $tauxConversion = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateMiseAJour = null;
}
