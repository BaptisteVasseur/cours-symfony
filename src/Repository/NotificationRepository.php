<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return list<Notification>
     */
    public function trouverPourUtilisateur(User $utilisateur): array
    {
        return $this->createQueryBuilder('notification')
            ->andWhere('notification.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $utilisateur)
            ->orderBy('notification.dateCreation', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    public function compterNonLues(User $utilisateur): int
    {
        return (int) $this->createQueryBuilder('notification')
            ->select('COUNT(notification.id)')
            ->andWhere('notification.utilisateur = :utilisateur')
            ->andWhere('notification.statut = :statut')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('statut', NotificationStatut::NON_LUE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
