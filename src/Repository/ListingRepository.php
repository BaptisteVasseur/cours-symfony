<?php

namespace App\Repository;

use App\Entity\Listing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Listing>
 */
class ListingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Listing::class);
    }

    /**
     * @return Listing[]
     */
    public function findAvailable(
        string $destination = '',
        ?\DateTimeInterface $checkin = null,
        ?\DateTimeInterface $checkout = null,
        int $guestsCount = 0,
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->where('l.isActive = true');

        if ($destination !== '') {
            $qb->andWhere('LOWER(l.city) LIKE :dest OR LOWER(l.country) LIKE :dest')
               ->setParameter('dest', '%' . mb_strtolower($destination) . '%');
        }

        if ($guestsCount > 0) {
            $qb->andWhere('l.maxGuests >= :guests')
               ->setParameter('guests', $guestsCount);
        }

        if ($checkin !== null && $checkout !== null) {
            $qb->andWhere(
                $qb->expr()->not(
                    $qb->expr()->exists(
                        'SELECT b FROM App\Entity\Booking b
                         WHERE b.listing = l
                         AND b.status = \'confirmed\'
                         AND b.startDate < :checkout
                         AND b.endDate > :checkin'
                    )
                )
            )->andWhere(
                $qb->expr()->not(
                    $qb->expr()->exists(
                        'SELECT bp FROM App\Entity\BlockedPeriod bp
                         WHERE bp.listing = l
                         AND bp.startDate < :checkout
                         AND bp.endDate > :checkin'
                    )
                )
            )
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout);
        }

        return $qb->orderBy('l.pricePerNight', 'ASC')->getQuery()->getResult();
    }
}
