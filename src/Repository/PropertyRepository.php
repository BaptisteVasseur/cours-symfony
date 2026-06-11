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
     * Recherche front : logements publiés correspondant à la destination, la capacité et,
     * si des dates sont fournies, réellement disponibles sur [checkin, checkout)
     * (aucune réservation confirmée chevauchante, aucun blocage hôte).
     *
     * @return list<Property>
     */
    public function findAvailableForSearch(
        ?string $destination,
        ?\DateTimeImmutable $checkin,
        ?\DateTimeImmutable $checkout,
        int $guests = 0,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('m', 'addr', 'r')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'addr')
            ->leftJoin('p.reviews', 'r')
            ->andWhere('p.status = :published')
            ->setParameter('published', 'published')
            ->orderBy('p.createdAt', 'DESC');

        if ($guests > 0) {
            $qb->andWhere('p.maxGuests >= :guests')->setParameter('guests', $guests);
        }

        if ($destination !== null && trim($destination) !== '') {
            $qb->andWhere('(
                    LOWER(addr.city) LIKE :dest
                    OR LOWER(addr.country) LIKE :dest
                    OR LOWER(addr.postalCode) LIKE :dest
                    OR LOWER(addr.addressLine1) LIKE :dest
                    OR LOWER(addr.addressLine2) LIKE :dest
                )')
                ->setParameter('dest', '%' . mb_strtolower(trim($destination)) . '%');
        }

        if ($checkin !== null && $checkout !== null && $checkout > $checkin) {
            $qb->andWhere('NOT EXISTS (SELECT res.id FROM App\Entity\Reservation res WHERE res.property = p AND res.status = :confirmed AND res.checkinDate < :checkout AND res.checkoutDate > :checkin)')
                ->andWhere('NOT EXISTS (SELECT av.id FROM App\Entity\PropertyAvailability av WHERE av.property = p AND av.isAvailable = false AND av.startDate < :checkout AND av.endDate >= :checkin)')
                ->setParameter('confirmed', 'confirmed')
                ->setParameter('checkin', $checkin)
                ->setParameter('checkout', $checkout);
        }

        return $qb->getQuery()->getResult();
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
}
