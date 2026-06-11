<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ProfilUtilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'profil', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $utilisateur;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $biographie = null;

    #[ORM\Column(type: Types::JSON)]
    public array $languesParlees = [];

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $profession = null;

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $villeResidence = null;

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $paysResidence = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $photoCouverture = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2)]
    public string $noteMoyenne = '0.00';

    #[ORM\Column]
    public int $nombreAvis = 0;

    #[ORM\Column]
    public bool $superHote = false;

    #[ORM\Column]
    public \DateTimeImmutable $dateMiseAJour;

    public function __construct()
    {
        $this->dateMiseAJour = new \DateTimeImmutable();
    }
}
