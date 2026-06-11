<?php

namespace App\Entity;

use App\Enum\NotificationStatut;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $utilisateur;

    #[ORM\Column(length: 40)]
    public string $type = 'systeme';

    #[ORM\Column(length: 180)]
    public string $titre = '';

    #[ORM\Column(type: Types::TEXT)]
    public string $contenu = '';

    #[ORM\Column(length: 40)]
    public string $canal = 'interne';

    #[ORM\Column(enumType: NotificationStatut::class)]
    public NotificationStatut $statut = NotificationStatut::NON_LUE;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $lienAction = null;

    #[ORM\Column]
    public \DateTimeImmutable $dateCreation;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateLecture = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }
}
