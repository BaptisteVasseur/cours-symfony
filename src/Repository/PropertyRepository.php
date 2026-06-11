<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
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
     * Annonces publiées, les plus récentes d'abord.
     *
     * @return Property[]
     */
    public function findPublished(int $limit = 12): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.address', 'a')->addSelect('a')
            ->leftJoin('p.media', 'm')->addSelect('m')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Moteur de recherche voyageur (Partie C).
     * Filtre en UNE requête : destination (ville/pays/adresse), disponibilité stricte
     * sur la plage de dates, et capacité d'accueil. Aucune itération logement par logement.
     *
     * @return Property[]
     */
    public function search(
        ?string $destination,
        ?\DateTimeImmutable $checkin,
        ?\DateTimeImmutable $checkout,
        ?int $guests,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.address', 'a')->addSelect('a')
            ->leftJoin('p.media', 'm')->addSelect('m')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('p.createdAt', 'DESC');

        if ($destination !== null && trim($destination) !== '') {
            $qb->andWhere('a.city LIKE :dest OR a.country LIKE :dest OR a.addressLine1 LIKE :dest')
                ->setParameter('dest', '%' . trim($destination) . '%');
        }

        if ($guests !== null && $guests > 0) {
            $qb->andWhere('p.maxGuests >= :guests')->setParameter('guests', $guests);
        }

        if ($checkin !== null && $checkout !== null && $checkout > $checkin) {
            $qb->andWhere(
                'NOT EXISTS (
                    SELECT 1 FROM ' . Reservation::class . ' res
                    WHERE res.property = p
                      AND res.status = :confirmed
                      AND res.checkinDate < :checkout
                      AND res.checkoutDate > :checkin
                )'
            );
            $qb->andWhere(
                'NOT EXISTS (
                    SELECT 1 FROM ' . PropertyAvailability::class . ' av
                    WHERE av.property = p
                      AND av.isAvailable = false
                      AND av.availableDate >= :checkin
                      AND av.availableDate < :checkout
                )'
            );
            $qb->setParameter('confirmed', Reservation::STATUS_CONFIRMED)
                ->setParameter('checkin', $checkin)
                ->setParameter('checkout', $checkout);
        }

        return $qb->getQuery()->getResult();
    }
}
