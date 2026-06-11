<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_favori_utilisateur_logement_liste', columns: ['utilisateur_id', 'logement_id', 'liste_id'])]
class Favori
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'favoris')]
    #[ORM\JoinColumn(nullable: false)]
    public User $utilisateur;

    #[ORM\ManyToOne(targetEntity: Logement::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Logement $logement;

    #[ORM\ManyToOne(targetEntity: ListeSouhaits::class)]
    public ?ListeSouhaits $liste = null;

    #[ORM\Column]
    public \DateTimeImmutable $dateAjout;

    public function __construct()
    {
        $this->dateAjout = new \DateTimeImmutable();
    }
}
