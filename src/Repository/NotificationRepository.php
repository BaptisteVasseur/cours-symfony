<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
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
     * Notifications in-app les plus récentes d'un utilisateur (cloche).
     *
     * @return list<Notification>
     */
    public function findRecentInApp(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->andWhere('n.channel = :channel')
            ->setParameter('user', $user)
            ->setParameter('channel', 'in_app')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadInApp(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.channel = :channel')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('channel', 'in_app')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllReadInApp(User $user): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->andWhere('n.user = :user')
            ->andWhere('n.channel = :channel')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('channel', 'in_app')
            ->getQuery()
            ->execute();
    }
}
