<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\User;
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


    /**
     * @return list<Property>
     */
    /**
     * @return list<Property>
     */
    public function findForListing(?string $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('m', 'a', 'r', 'host', 'hostProfile')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.reviews', 'r')
            ->leftJoin('p.host', 'host')
            ->leftJoin('host.profile', 'hostProfile')
            ->orderBy('p.createdAt', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Property>
     */
    public function findPendingForModeration(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('m', 'a', 'host', 'hostProfile')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.host', 'host')
            ->leftJoin('host.profile', 'hostProfile')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<Property>
     */
    public function findMostPopular(): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('m', 'a', 'r')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.reviews', 'r')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();
    }

    public function findOneForDetail(Property $property): ?Property
    {
        return $this->createQueryBuilder('p')
            ->addSelect('m', 'a', 'r', 'reviewer', 'profile', 'host', 'hostProfile', 'pa', 'amenity')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.reviews', 'r')
            ->leftJoin('r.reviewer', 'reviewer')
            ->leftJoin('reviewer.profile', 'profile')
            ->leftJoin('p.host', 'host')
            ->leftJoin('host.profile', 'hostProfile')
            ->leftJoin('p.propertyAmenities', 'pa')
            ->leftJoin('pa.amenity', 'amenity')
            ->andWhere('p = :property')
            ->setParameter('property', $property)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Property>
     */
    public function findByHost(User $host): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('m', 'a')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->andWhere('p.host = :host')
            ->setParameter('host', $host)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Searches published properties with optional filters for availability.
     *
     * Filters:
     *   - destination: text search on city or address line
     *   - checkin + checkout: excludes properties with confirmed reservations
     *     or manual blocks overlapping the date range
     *   - guests: excludes properties with insufficient maxGuests
     *
     * Uses NOT EXISTS subqueries instead of JOINs to avoid row multiplication
     * and incorrect DISTINCT results.
     *
     * @param array{destination?: ?string, checkin?: ?\DateTimeImmutable, checkout?: ?\DateTimeImmutable, guests?: ?int} $filters
     * @return list<Property>
     */
    public function findAvailable(array $filters): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('m', 'a', 'r', 'host', 'hostProfile')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.reviews', 'r')
            ->leftJoin('p.host', 'host')
            ->leftJoin('host.profile', 'hostProfile')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'published');

        // Filter by guest capacity
        if (!empty($filters['guests']) && $filters['guests'] > 0) {
            $qb->andWhere('p.maxGuests >= :guests')
                ->setParameter('guests', $filters['guests']);
        }

        // Filter by destination (city or address line)
        if (!empty($filters['destination'])) {
            $qb->andWhere('(a.city LIKE :dest OR a.addressLine1 LIKE :dest)')
                ->setParameter('dest', '%' . $filters['destination'] . '%');
        }

        // Filter by availability — exclude properties with confirmed overlapping reservations
        if (!empty($filters['checkin']) && !empty($filters['checkout'])) {
            $qb->andWhere('NOT EXISTS (
                SELECT res FROM App\Entity\Reservation res
                WHERE res.property = p
                AND res.status = :confirmedStatus
                AND res.checkinDate < :checkout
                AND res.checkoutDate > :checkin
            )')
                ->setParameter('confirmedStatus', 'confirmed')
                ->setParameter('checkin', $filters['checkin'])
                ->setParameter('checkout', $filters['checkout']);

            // Exclude properties with manually blocked days in the range
            $qb->andWhere('NOT EXISTS (
                SELECT pa FROM App\Entity\PropertyAvailability pa
                WHERE pa.property = p
                AND pa.isAvailable = false
                AND pa.availableDate >= :checkin
                AND pa.availableDate < :checkout
            )');
        }

        return $qb->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
