<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationStatut;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function creer(User $utilisateur, string $type, string $titre, string $contenu, ?string $lienAction = null): Notification
    {
        $notification = new Notification();
        $notification->utilisateur = $utilisateur;
        $notification->type = $type;
        $notification->titre = $titre;
        $notification->contenu = $contenu;
        $notification->lienAction = $lienAction;

        $this->entityManager->persist($notification);

        return $notification;
    }

    public function marquerCommeLue(Notification $notification): void
    {
        if ($notification->statut === NotificationStatut::LUE) {
            return;
        }

        $notification->statut = NotificationStatut::LUE;
        $notification->dateLecture = new \DateTimeImmutable();
    }
}
