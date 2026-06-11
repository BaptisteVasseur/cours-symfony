<?php

namespace App\Entity;

use App\Enum\ConversationStatut;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    public ?Reservation $reservation = null;

    #[ORM\ManyToOne(targetEntity: Logement::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Logement $logement;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $voyageur;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $hote;

    #[ORM\Column(enumType: ConversationStatut::class)]
    public ConversationStatut $statut = ConversationStatut::ACTIVE;

    #[ORM\Column]
    public \DateTimeImmutable $dateCreation;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateDernierMessage = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }
}
