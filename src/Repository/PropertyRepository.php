<?php

namespace App\Repository;

use App\Entity\Property;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;

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
         * @return Property[] Returns the 10 most popular properties
         */
        public function findPopulars(): array
        {
            return $this->createQueryBuilder('p')
                ->orderBy('p.note', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult()
            ;
        }

        /**
         * @return Property[] Returns an array of Property objects based on search criteria
         */
        public function search(Request $request): array
        {
            $location = $request->query->get('location');
            $checkIn = $request->query->get('checkin');
            $checkOut = $request->query->get('checkout');
            $guests = $request->query->get('guests');
            $currentPage = $request->query->get('page', 1);
            $itemsPerPage = 3;

            $qb = $this->createQueryBuilder('p');

            if ($location) {
                $qb->andWhere('p.city LIKE :location OR p.address LIKE :location')
                   ->setParameter('location', '%' . $location . '%');
            }

            if ($guests) {
                $qb->andWhere('p.maxGuests >= :guests AND p.maxGuests <= :guests + 2')
                    ->setParameter('guests', $guests);
            }

            $qb->orderBy('p.note', 'DESC');
            $qb->addOrderBy('p.maxGuests', 'ASC');

//            $qb->setFirstResult(($currentPage - 1) * $itemsPerPage)
//               ->setMaxResults($itemsPerPage);

            return $qb->getQuery()->getResult();
        }
}
