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
     * @param array{
     *     destination?: ?string,
     *     guests?: ?int,
     *     priceMin?: ?int,
     *     priceMax?: ?int,
     *     propertyType?: ?string,
     *     sort?: ?string,
     * } $criteria
     * @return list<Property>
     */
    public function searchPublished(array $criteria): array
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

        $destination = isset($criteria['destination']) ? trim((string) $criteria['destination']) : '';
        if ($destination !== '') {
            $qb->andWhere('LOWER(a.city) LIKE :destination OR LOWER(a.country) LIKE :destination')
                ->setParameter('destination', '%' . mb_strtolower($destination) . '%');
        }

        if (!empty($criteria['guests']) && $criteria['guests'] > 0) {
            $qb->andWhere('p.maxGuests >= :guests')
                ->setParameter('guests', (int) $criteria['guests']);
        }

        if (!empty($criteria['priceMin']) && $criteria['priceMin'] > 0) {
            $qb->andWhere('p.pricePerNight >= :priceMin')
                ->setParameter('priceMin', (int) $criteria['priceMin']);
        }

        if (!empty($criteria['priceMax']) && $criteria['priceMax'] > 0) {
            $qb->andWhere('p.pricePerNight <= :priceMax')
                ->setParameter('priceMax', (int) $criteria['priceMax']);
        }

        if (!empty($criteria['propertyType'])) {
            $qb->andWhere('p.propertyType = :propertyType')
                ->setParameter('propertyType', (string) $criteria['propertyType']);
        }

        $sort = $criteria['sort'] ?? null;
        if ($sort === 'price_asc') {
            $qb->orderBy('p.pricePerNight', 'ASC');
        } elseif ($sort === 'price_desc') {
            $qb->orderBy('p.pricePerNight', 'DESC');
        } else {
            $qb->orderBy('p.createdAt', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }
}
