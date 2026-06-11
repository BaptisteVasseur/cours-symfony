<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\Reservation;
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
     * Filtered search for published properties.
     * Excludes properties with confirmed reservations overlapping [checkin, checkout).
     * Excludes properties with any blocked date in that range.
     * Filters by maxGuests >= $guests and city/address ILIKE $destination.
     *
     * @return list<Property>
     */
    public function findForSearch(
        ?string $destination,
        ?\DateTimeImmutable $checkin,
        ?\DateTimeImmutable $checkout,
        ?int $guests,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('m', 'a', 'host', 'hostProfile')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.host', 'host')
            ->leftJoin('host.profile', 'hostProfile')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('p.createdAt', 'DESC');

        if ($destination !== null && $destination !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(a.city)', ':dest'),
                    $qb->expr()->like('LOWER(a.addressLine1)', ':dest'),
                )
            )->setParameter('dest', '%' . mb_strtolower($destination) . '%');
        }

        if ($guests !== null && $guests > 0) {
            $qb->andWhere('p.maxGuests >= :guests')->setParameter('guests', $guests);
        }

        if ($checkin !== null && $checkout !== null) {
            // Exclude properties with a confirmed reservation overlapping the range
            $qb->andWhere(
                $qb->expr()->not(
                    $qb->expr()->exists(
                        $this->getEntityManager()->createQueryBuilder()
                            ->select('1')
                            ->from(Reservation::class, 'res')
                            ->where('res.property = p')
                            ->andWhere('res.status = :resStatus')
                            ->andWhere('res.checkinDate < :checkout')
                            ->andWhere('res.checkoutDate > :checkin')
                            ->getDQL()
                    )
                )
            )
            ->setParameter('resStatus', 'confirmed')
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout);

            // Exclude properties with any manually blocked date in range
            $qb->andWhere(
                $qb->expr()->not(
                    $qb->expr()->exists(
                        $this->getEntityManager()->createQueryBuilder()
                            ->select('1')
                            ->from(\App\Entity\PropertyAvailability::class, 'pa')
                            ->where('pa.property = p')
                            ->andWhere('pa.isAvailable = false')
                            ->andWhere('pa.availableDate >= :checkin')
                            ->andWhere('pa.availableDate < :checkout')
                            ->getDQL()
                    )
                )
            );
        }

        return $qb->getQuery()->getResult();
    }
}
