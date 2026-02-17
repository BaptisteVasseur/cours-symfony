<?php

namespace App\Repository;

use App\Entity\Property;
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

    public function search(?string $city, ?string $startAt, ?string $endAt, ?int $travelers, ?int $page = 1, ?int $numberOfElementsPerPage = 10): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($city) {
            $qb
                ->where('p.city LIKE :city')
                ->setParameter('city', '%' . $city . '%');
        }

        if ($travelers) {
            $qb
                ->andWhere('p.maxGuests >= :travelers')
                ->setParameter('travelers', $travelers);;
        }


        if ($startAt || $endAt) {
            $qb->innerJoin('p.availabilities', 'a');

            if ($startAt) {
                $qb
                    ->andWhere('a.startAt <= :startAt')
                    ->setParameter('startAt', $startAt);
            }

            if ($endAt) {
                $qb
                    ->andWhere('a.endAt >= :endAt')
                    ->setParameter('endAt', $endAt);
            }
        }

        if ($page) {
            $qb
                ->setFirstResult(($page - 1) * $numberOfElementsPerPage)
                ->setMaxResults($numberOfElementsPerPage);
        }

        return $qb->getQuery()->getResult();
    }

//    /**
//     * @return Property[] Returns an array of Property objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Property
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
