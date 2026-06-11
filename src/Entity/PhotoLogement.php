<?php

namespace App\Entity;

use App\Enum\ModerationStatut;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PhotoLogement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Logement::class, inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false)]
    public Logement $logement;

    #[ORM\Column(length: 255)]
    public string $url = '';

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $titre = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $description = null;

    #[ORM\Column]
    public int $ordreAffichage = 0;

    #[ORM\Column]
    public bool $photoPrincipale = false;

    #[ORM\Column(enumType: ModerationStatut::class)]
    public ModerationStatut $statutModeration = ModerationStatut::EN_ATTENTE;

    #[ORM\Column]
    public \DateTimeImmutable $dateUpload;

    public function __construct()
    {
        $this->dateUpload = new \DateTimeImmutable();
    }
}
