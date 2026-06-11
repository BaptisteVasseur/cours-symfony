<?php

namespace App\Entity;

use App\Enum\MessageStatut;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Conversation $conversation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $expediteur;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $contenu = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $pieceJointeUrl = null;

    #[ORM\Column(enumType: MessageStatut::class)]
    public MessageStatut $statut = MessageStatut::ENVOYE;

    #[ORM\Column]
    public \DateTimeImmutable $dateEnvoi;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateLecture = null;

    public function __construct()
    {
        $this->dateEnvoi = new \DateTimeImmutable();
    }
}
