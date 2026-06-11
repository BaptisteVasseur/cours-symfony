<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AvailabilityBlock;
use App\Entity\Booking;
use App\Entity\Listing;
use App\Enum\BookingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;


class ListingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Listing::class);
    }

    public function findForUpdate(string $id): ?Listing
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    public function search(
        ?string $destination,
        ?\DateTimeImmutable $checkIn,
        ?\DateTimeImmutable $checkOut,
        ?int $guests,
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.location', 'loc')
            ->addSelect('loc')
            ->andWhere('l.status = :published')
            ->setParameter('published', 'published')
            ->orderBy('l.createdAt', 'DESC');

        if ($destination !== null && trim($destination) !== '') {
            $qb->andWhere('LOWER(loc.city) LIKE :dest OR LOWER(loc.addressLine1) LIKE :dest OR LOWER(l.title) LIKE :dest')
                ->setParameter('dest', '%' . mb_strtolower(trim($destination)) . '%');
        }

        if ($guests !== null && $guests > 0) {
            $qb->andWhere('l.maxGuests >= :guests')->setParameter('guests', $guests);
        }

        if ($checkIn !== null && $checkOut !== null) {
            $confirmedOverlap = $this->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from(Booking::class, 'b')
                ->where('b.listing = l')
                ->andWhere('b.bookingStatus = :confirmed')
                ->andWhere('b.checkIn < :checkOut')
                ->andWhere('b.checkOut > :checkIn');

            $blockOverlap = $this->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from(AvailabilityBlock::class, 'ab')
                ->where('ab.listing = l')
                ->andWhere('ab.startDate < :checkOut')
                ->andWhere('ab.endDate > :checkIn');

            $qb->andWhere($qb->expr()->not($qb->expr()->exists($confirmedOverlap->getDQL())))
                ->andWhere($qb->expr()->not($qb->expr()->exists($blockOverlap->getDQL())))
                ->setParameter('confirmed', BookingStatus::Confirmed)
                ->setParameter('checkIn', $checkIn->setTime(0, 0))
                ->setParameter('checkOut', $checkOut->setTime(0, 0));
        }

        return $qb->getQuery()->getResult();
    }
}
