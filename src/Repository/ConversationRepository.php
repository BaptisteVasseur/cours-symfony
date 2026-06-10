<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * @return list<Conversation>
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('r', 'p', 'part', 'u', 'up', 'msg', 'sender', 'senderProfile')
            ->innerJoin('c.participants', 'part')
            ->innerJoin('part.user', 'u')
            ->leftJoin('u.profile', 'up')
            ->leftJoin('c.reservation', 'r')
            ->leftJoin('r.property', 'p')
            ->leftJoin('c.messages', 'msg')
            ->leftJoin('msg.sender', 'sender')
            ->leftJoin('sender.profile', 'senderProfile')
            ->andWhere('u = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUser(Conversation $conversation, User $user): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->addSelect('r', 'p', 'part', 'u', 'up', 'msg', 'sender', 'senderProfile', 'guest', 'guestProfile', 'host', 'hostProfile')
            ->innerJoin('c.participants', 'part')
            ->innerJoin('part.user', 'u')
            ->leftJoin('u.profile', 'up')
            ->leftJoin('c.reservation', 'r')
            ->leftJoin('r.property', 'p')
            ->leftJoin('r.guest', 'guest')
            ->leftJoin('guest.profile', 'guestProfile')
            ->leftJoin('p.host', 'host')
            ->leftJoin('host.profile', 'hostProfile')
            ->leftJoin('c.messages', 'msg')
            ->leftJoin('msg.sender', 'sender')
            ->leftJoin('sender.profile', 'senderProfile')
            ->andWhere('c = :conversation')
            ->andWhere('u = :user')
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $user)
            ->orderBy('msg.createdAt', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
