<?php

namespace App\Repository;

use App\Entity\Property;
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
}
