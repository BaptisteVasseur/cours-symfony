<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ReglementInterieur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'reglementInterieur', targetEntity: Logement::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Logement $logement;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    public ?\DateTimeInterface $arriveeAPartirDe = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    public ?\DateTimeInterface $departAvant = null;

    #[ORM\Column]
    public bool $animauxAcceptes = false;

    #[ORM\Column]
    public bool $fumeursAcceptes = false;

    #[ORM\Column]
    public bool $fetesAutorisees = false;

    #[ORM\Column]
    public bool $enfantsAcceptes = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $reglesSupplementaires = null;
}
