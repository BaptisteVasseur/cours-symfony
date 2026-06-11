<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyBlock>
 */
class PropertyBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyBlock::class);
    }

    public function countOverlapping(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.property = :property')
            ->andWhere('b.dateStart < :checkout')
            ->andWhere('b.dateEnd > :checkin')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<PropertyBlock> */
    public function findForProperty(Property $property): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.property = :property')
            ->andWhere('b.dateEnd >= :today')
            ->setParameter('property', $property)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('b.dateStart', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<PropertyBlock> */
    public function findAllForProperty(Property $property): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.property = :property')
            ->setParameter('property', $property)
            ->orderBy('b.dateStart', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<PropertyBlock> */
    public function findByICalSource(Property $property, string $providerName): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.property = :property')
            ->andWhere('b.iCalUid LIKE :prefix')
            ->setParameter('property', $property)
            ->setParameter('prefix', $providerName.':%')
            ->getQuery()
            ->getResult();
    }
}
