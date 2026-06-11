<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class NoteDetaillee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'noteDetaillee', targetEntity: Avis::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Avis $avis;

    #[ORM\Column]
    public int $proprete = 5;

    #[ORM\Column]
    public int $precisionAnnonce = 5;

    #[ORM\Column]
    public int $arrivee = 5;

    #[ORM\Column]
    public int $communication = 5;

    #[ORM\Column]
    public int $emplacement = 5;

    #[ORM\Column]
    public int $rapportQualitePrix = 5;
}
