<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Enum\BookingStatus;
use App\Enum\PropertyStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Property>
 */
class PropertyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Property::class);
    }

    public function findPublished(string $city = null, int $guests = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.images', 'i')
            ->leftJoin('p.reviews', 'r')
            ->addSelect('i', 'r')
            ->where('p.status = :status')
            ->setParameter('status', PropertyStatus::PUBLISHED)
            ->orderBy('p.createdAt', 'DESC');

        if ($city) {
            $qb->andWhere('LOWER(p.city) LIKE LOWER(:city)')
               ->setParameter('city', '%' . $city . '%');
        }

        if ($guests) {
            $qb->andWhere('p.maxGuests >= :guests')
               ->setParameter('guests', $guests);
        }

        return $qb->getQuery()->getResult();
    }

    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.host', 'h')->addSelect('h')
            ->leftJoin('p.images', 'i')->addSelect('i')
            ->leftJoin('p.bookings', 'b')->addSelect('b')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search published properties by destination, dates and guests.
     * Uses NOT EXISTS subqueries to exclude unavailable properties in a single query.
     */
    public function findForSearch(
        ?string $destination,
        ?\DateTimeImmutable $checkIn,
        ?\DateTimeImmutable $checkOut,
        ?int $guests,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.images', 'i')->addSelect('i')
            ->leftJoin('p.reviews', 'r')->addSelect('r')
            ->where('p.status = :published')
            ->setParameter('published', PropertyStatus::PUBLISHED);

        if ($destination) {
            $qb->andWhere('LOWER(p.city) LIKE LOWER(:dest) OR LOWER(p.address) LIKE LOWER(:dest)')
               ->setParameter('dest', '%' . $destination . '%');
        }

        if ($guests) {
            $qb->andWhere('p.maxGuests >= :guests')
               ->setParameter('guests', $guests);
        }

        if ($checkIn && $checkOut) {
            $em = $this->getEntityManager();

            $subBooking = $em->createQueryBuilder()
                ->select('1')
                ->from(Booking::class, 'b2')
                ->where('b2.property = p')
                ->andWhere('b2.status = :confirmed')
                ->andWhere('b2.checkIn < :checkOut AND b2.checkOut > :checkIn')
                ->getDQL();

            $subBlocked = $em->createQueryBuilder()
                ->select('1')
                ->from(PropertyAvailability::class, 'pa2')
                ->where('pa2.property = p')
                ->andWhere('pa2.startDate < :checkOut AND pa2.endDate > :checkIn')
                ->getDQL();

            $qb->andWhere($qb->expr()->not($qb->expr()->exists($subBooking)))
               ->andWhere($qb->expr()->not($qb->expr()->exists($subBlocked)))
               ->setParameter('confirmed', BookingStatus::CONFIRMED)
               ->setParameter('checkIn', $checkIn)
               ->setParameter('checkOut', $checkOut);
        }

        return $qb->orderBy('p.createdAt', 'DESC')->getQuery()->getResult();
    }
}
