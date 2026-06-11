<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Langue
{
    #[ORM\Id]
    #[ORM\Column(length: 10)]
    public string $code = '';

    #[ORM\Column(length: 100)]
    public string $nom = '';

    #[ORM\Column]
    public bool $actif = true;

    #[ORM\Column]
    public bool $langueParDefaut = false;
}
