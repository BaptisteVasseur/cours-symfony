<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PropertyAmenity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyAmenity>
 */
class PropertyAmenityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyAmenity::class);
    }
}
