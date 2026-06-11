<?php

namespace App\Entity;

use App\Enum\TraitementStatut;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Signalement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $auteur;

    #[ORM\Column(length: 40)]
    public string $cibleType = 'utilisateur';

    #[ORM\Column]
    public int $cibleId;

    #[ORM\Column(length: 80)]
    public string $motif = 'autre';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(enumType: TraitementStatut::class)]
    public TraitementStatut $statut = TraitementStatut::NOUVEAU;

    #[ORM\ManyToOne(targetEntity: User::class)]
    public ?User $administrateur = null;

    #[ORM\Column]
    public \DateTimeImmutable $dateCreation;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateTraitement = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }
}
