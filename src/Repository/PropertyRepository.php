<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
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

    public function countForHost(\App\Entity\User $host): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.host = :host')
            ->setParameter('host', $host)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Property>
     */
    public function findForHostDashboard(\App\Entity\User $host): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('m', 'a', 'r', 'guest', 'guestProfile')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.reservations', 'r')
            ->leftJoin('r.guest', 'guest')
            ->leftJoin('guest.profile', 'guestProfile')
            ->andWhere('p.host = :host')
            ->setParameter('host', $host)
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('r.checkinDate', 'ASC')
            ->getQuery()
            ->getResult();
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
    public function searchAvailable(
        ?string $destination,
        ?\DateTimeImmutable $checkinDate,
        ?\DateTimeImmutable $checkoutDate,
        ?int $guests,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('m', 'a', 'r')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.reviews', 'r')
            ->andWhere('p.status = :published')
            ->setParameter('published', 'published')
            ->orderBy('p.createdAt', 'DESC');

        if ($destination !== null && trim($destination) !== '') {
            $qb->andWhere('LOWER(a.city) LIKE :destination OR LOWER(a.addressLine1) LIKE :destination')
                ->setParameter('destination', '%'.mb_strtolower(trim($destination)).'%');
        }

        if ($guests !== null && $guests > 0) {
            $qb->andWhere('p.maxGuests >= :guests')
                ->setParameter('guests', $guests);
        }

        if ($checkinDate !== null && $checkoutDate !== null && $checkoutDate > $checkinDate) {
            $qb->andWhere(sprintf(
                'NOT EXISTS (SELECT reserved.id FROM %s reserved WHERE reserved.property = p AND reserved.status = :confirmed AND reserved.checkinDate < :checkoutDate AND reserved.checkoutDate > :checkinDate)',
                Reservation::class,
            ))
                ->andWhere(sprintf(
                    'NOT EXISTS (SELECT blocked.id FROM %s blocked WHERE blocked.property = p AND blocked.isAvailable = false AND blocked.availableDate >= :checkinDate AND blocked.availableDate < :checkoutDate)',
                    PropertyAvailability::class,
                ))
                ->setParameter('confirmed', 'confirmed')
                ->setParameter('checkinDate', $checkinDate)
                ->setParameter('checkoutDate', $checkoutDate);
        }

        return $qb->getQuery()->getResult();
    }
}
