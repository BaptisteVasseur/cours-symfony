<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BookingStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookingStatusHistory>
 */
class BookingStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingStatusHistory::class);
    }
}
