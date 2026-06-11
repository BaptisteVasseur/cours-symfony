<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AvailabilityBlock;
use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
    public function findForListing(?string $status = null, ?string $category = null): array
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

        $this->applyCategoryFilter($qb, $category);
        $this->applyCategoryOrdering($qb, $category);

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

    /**
     * @return list<Property>
     */
    public function searchAvailable(
        ?string $destination,
        ?\DateTimeImmutable $checkin,
        ?\DateTimeImmutable $checkout,
        int $guests,
        ?string $category = null,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('m', 'a', 'r', 'host', 'hostProfile')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.reviews', 'r')
            ->leftJoin('p.host', 'host')
            ->leftJoin('host.profile', 'hostProfile')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('p.createdAt', 'DESC');

        $destination = trim((string) $destination);
        if ($destination !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(p.title) LIKE :destination',
                    'LOWER(a.city) LIKE :destination',
                    'LOWER(a.addressLine1) LIKE :destination',
                    'LOWER(a.addressLine2) LIKE :destination',
                ),
            )
                ->setParameter('destination', '%'.mb_strtolower($destination).'%');
        }

        if ($guests > 0) {
            $qb->andWhere('p.maxGuests >= :guests')
                ->setParameter('guests', $guests);
        }

        $this->applyCategoryFilter($qb, $category);

        if ($checkin !== null && $checkout !== null) {
            $reservationClass = Reservation::class;
            $blockClass = AvailabilityBlock::class;

            $qb->andWhere(sprintf(
                'NOT EXISTS (
                    SELECT reservationConflict.id
                    FROM %s reservationConflict
                    WHERE reservationConflict.property = p
                    AND reservationConflict.status = :confirmedStatus
                    AND reservationConflict.checkinDate < :checkout
                    AND reservationConflict.checkoutDate > :checkin
                )',
                $reservationClass,
            ))
                ->andWhere(sprintf(
                    'NOT EXISTS (
                        SELECT blockConflict.id
                        FROM %s blockConflict
                        WHERE blockConflict.property = p
                        AND blockConflict.startDate < :checkout
                        AND blockConflict.endDate > :checkin
                    )',
                    $blockClass,
                ))
                ->setParameter('confirmedStatus', 'confirmed')
                ->setParameter('checkin', $checkin)
                ->setParameter('checkout', $checkout);
        }

        $this->applyCategoryOrdering($qb, $category);

        return $qb->getQuery()->getResult();
    }

    private function applyCategoryFilter(QueryBuilder $qb, ?string $category): void
    {
        match ($category) {
            'seaside' => $this->applyKeywordCategory($qb, 'seaside', [
                'mer',
                'plage',
                'océan',
                'ocean',
                'littoral',
                'côte',
                'cote',
                'port',
                'arcachon',
                'deauville',
                'santorin',
            ]),
            'iconic' => $qb
                ->andWhere($qb->expr()->orX(
                    'p.propertyType IN (:iconicTypes)',
                    $this->buildKeywordExpression($qb, 'iconic', [
                        'luxe',
                        'vue',
                        'design',
                        'penthouse',
                        'alpin',
                        'paris',
                        'santorin',
                    ]),
                ))
                ->setParameter('iconicTypes', ['villa', 'loft', 'chalet']),
            'trending' => $qb->andWhere('r.id IS NOT NULL'),
            'camping' => $qb
                ->andWhere($qb->expr()->orX(
                    'p.propertyType IN (:campingTypes)',
                    $this->buildKeywordExpression($qb, 'camping', [
                        'camping',
                        'campagne',
                        'gîte',
                        'gite',
                        'chalet',
                        'bungalow',
                        'rural',
                        'nature',
                        'provence',
                        'normande',
                    ]),
                ))
                ->setParameter('campingTypes', ['house', 'chalet']),
            default => null,
        };
    }

    private function applyKeywordCategory(QueryBuilder $qb, string $prefix, array $keywords): void
    {
        $qb->andWhere($this->buildKeywordExpression($qb, $prefix, $keywords));
    }

    private function buildKeywordExpression(QueryBuilder $qb, string $prefix, array $keywords): string
    {
        $fields = [
            'LOWER(p.title)',
            'LOWER(p.description)',
            'LOWER(a.city)',
            'LOWER(a.country)',
            'LOWER(a.addressLine1)',
            'LOWER(a.addressLine2)',
        ];
        $conditions = [];

        foreach ($keywords as $index => $keyword) {
            $parameter = sprintf('%sKeyword%d', $prefix, $index);
            foreach ($fields as $field) {
                $conditions[] = sprintf('%s LIKE :%s', $field, $parameter);
            }
            $qb->setParameter($parameter, '%'.mb_strtolower($keyword).'%');
        }

        return (string) $qb->expr()->orX(...$conditions);
    }

    private function applyCategoryOrdering(QueryBuilder $qb, ?string $category): void
    {
        if ($category === 'trending') {
            $qb->addOrderBy('r.createdAt', 'DESC');
        }
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
     * @return list<Property>
     */
    public function findWithExternalIcalUrl(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.externalIcalUrl IS NOT NULL')
            ->andWhere('p.externalIcalUrl <> :empty')
            ->setParameter('empty', '')
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
