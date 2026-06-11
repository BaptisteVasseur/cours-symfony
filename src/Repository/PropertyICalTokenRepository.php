<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyICalToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyICalToken>
 */
class PropertyICalTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyICalToken::class);
    }

    /**
     * Find a valid (non-revoked) token by property and token string.
     */
    public function findValidToken(Property $property, string $token): ?PropertyICalToken
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.property = :property')
            ->andWhere('t.token = :token')
            ->andWhere('t.revokedAt IS NULL')
            ->setParameter('property', $property)
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the first valid token for a property.
     */
    public function findFirstValidToken(Property $property): ?PropertyICalToken
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.property = :property')
            ->andWhere('t.revokedAt IS NULL')
            ->setParameter('property', $property)
            ->orderBy('t.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all valid tokens for a property.
     *
     * @return list<PropertyICalToken>
     */
    public function findValidTokensByProperty(Property $property): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.property = :property')
            ->andWhere('t.revokedAt IS NULL')
            ->setParameter('property', $property)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
