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
    public function findForListing(
        ?string $status = null,
        ?string $propertyType = null,
        ?string $search = null,
        string $sort = 'createdAt',
        string $dir = 'DESC',
    ): array {
        $allowedSorts = ['title' => 'p.title', 'status' => 'p.status', 'pricePerNight' => 'p.pricePerNight', 'createdAt' => 'p.createdAt'];
        $orderCol = $allowedSorts[$sort] ?? 'p.createdAt';
        $orderDir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('p')
            ->addSelect('m', 'a', 'r', 'host', 'hostProfile')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.reviews', 'r')
            ->leftJoin('p.host', 'host')
            ->leftJoin('host.profile', 'hostProfile')
            ->orderBy($orderCol, $orderDir);

        if ($status !== null && $status !== '') {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        if ($propertyType !== null && $propertyType !== '') {
            $qb->andWhere('p.propertyType = :propertyType')
                ->setParameter('propertyType', $propertyType);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('p.title LIKE :search OR a.city LIKE :search OR a.country LIKE :search')
                ->setParameter('search', '%' . $search . '%');
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

    /**
     * @return list<Property>
     */
    public function findAvailableForSearch(
        ?string $destination,
        ?\DateTimeImmutable $checkIn,
        ?\DateTimeImmutable $checkOut,
        int $guests = 1,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('m', 'a', 'r', 'host', 'hostProfile')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.reviews', 'r')
            ->leftJoin('p.host', 'host')
            ->leftJoin('host.profile', 'hostProfile')
            ->andWhere('p.status = :published')
            ->andWhere('p.maxGuests >= :guests')
            ->setParameter('published', 'published')
            ->setParameter('guests', $guests)
            ->orderBy('p.createdAt', 'DESC');

        if ($destination !== null && $destination !== '') {
            $qb->andWhere('LOWER(a.city) LIKE LOWER(:destination) OR LOWER(a.addressLine1) LIKE LOWER(:destination)')
               ->setParameter('destination', '%' . strtolower($destination) . '%');
        }

        if ($checkIn !== null && $checkOut !== null) {
            // Exclure les logements avec une réservation CONFIRMED qui chevauche
            $qb->andWhere(
                $qb->expr()->not(
                    $qb->expr()->exists(
                        $this->getEntityManager()->createQueryBuilder()
                            ->select('1')
                            ->from(\App\Entity\Reservation::class, 'res')
                            ->where('res.property = p')
                            ->andWhere('res.status = :confirmed')
                            ->andWhere('res.checkinDate < :checkOut')
                            ->andWhere('res.checkoutDate > :checkIn')
                            ->getDQL()
                    )
                )
            )
            // Exclure les logements avec une date bloquée dans la plage
            ->andWhere(
                $qb->expr()->not(
                    $qb->expr()->exists(
                        $this->getEntityManager()->createQueryBuilder()
                            ->select('1')
                            ->from(\App\Entity\PropertyAvailability::class, 'av')
                            ->where('av.property = p')
                            ->andWhere('av.isAvailable = false')
                            ->andWhere('av.availableDate >= :checkIn')
                            ->andWhere('av.availableDate < :checkOut')
                            ->getDQL()
                    )
                )
            )
            ->setParameter('confirmed', 'confirmed')
            ->setParameter('checkIn', $checkIn)
            ->setParameter('checkOut', $checkOut);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<Property> */
    public function findByHost(User $host): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('m', 'a')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->andWhere('p.host = :host')
            ->setParameter('host', $host)
            ->orderBy('a.country', 'ASC')
            ->addOrderBy('a.city', 'ASC')
            ->addOrderBy('p.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForDetail(Property $property): ?Property
    {
        return $this->createQueryBuilder('p')
            ->addSelect('m', 'a', 'r', 'reviewer', 'profile', 'host', 'hostProfile', 'pa', 'amenity', 'av')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.reviews', 'r')
            ->leftJoin('r.reviewer', 'reviewer')
            ->leftJoin('reviewer.profile', 'profile')
            ->leftJoin('p.host', 'host')
            ->leftJoin('host.profile', 'hostProfile')
            ->leftJoin('p.propertyAmenities', 'pa')
            ->leftJoin('pa.amenity', 'amenity')
            ->leftJoin('p.availabilities', 'av', 'WITH', 'av.availableDate >= :today')
            ->andWhere('p = :property')
            ->setParameter('property', $property)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
