<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AvailabilityChecker
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function assertBookable(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout, int $guests): void
    {
        if ($property->getStatus() !== 'published') {
            throw new \DomainException('Ce logement n’est pas disponible à la réservation.');
        }

        if ($checkin < new \DateTimeImmutable('today')) {
            throw new \DomainException('La date d’arrivée ne peut pas être passée.');
        }

        if ($checkout <= $checkin) {
            throw new \DomainException('La date de départ doit être postérieure à la date d’arrivée.');
        }

        if ($guests < 1) {
            throw new \DomainException('Le nombre de voyageurs doit être supérieur à zéro.');
        }

        if ($property->getMaxGuests() !== null && $guests > $property->getMaxGuests()) {
            throw new \DomainException('Ce logement ne peut pas accueillir autant de voyageurs.');
        }

        if ($this->hasBlockedNight($property, $checkin, $checkout)) {
            throw new \DomainException('Le logement est indisponible sur au moins une nuit demandée.');
        }

        if ($this->hasConfirmedOverlap($property, $checkin, $checkout)) {
            throw new \DomainException('Une réservation confirmée existe déjà sur ces dates.');
        }
    }

    private function hasBlockedNight(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): bool
    {
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(PropertyAvailability::class, 'a')
            ->andWhere('a.property = :property')
            ->andWhere('a.availableDate >= :checkin')
            ->andWhere('a.availableDate < :checkout')
            ->andWhere('a.isAvailable = false')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    private function hasConfirmedOverlap(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): bool
    {
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reservation::class, 'r')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkinDate < :checkout')
            ->andWhere('r.checkoutDate > :checkin')
            ->setParameter('property', $property)
            ->setParameter('status', 'confirmed')
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
