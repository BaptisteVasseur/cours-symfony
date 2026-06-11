<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BookingPriceCalculator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function calculate(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): BookingPriceBreakdown
    {
        $nights = (int) $checkin->diff($checkout)->days;
        if ($nights <= 0) {
            throw new \DomainException('La date de départ doit être postérieure à la date d’arrivée.');
        }

        $overrides = $this->findPriceOverrides($property, $checkin, $checkout);
        $basePrice = (float) $property->getPricePerNight();
        $nightsAmount = 0.0;
        $cursor = $checkin;

        while ($cursor < $checkout) {
            $key = $cursor->format('Y-m-d');
            $nightsAmount += $overrides[$key] ?? $basePrice;
            $cursor = $cursor->modify('+1 day');
        }

        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $securityDeposit = (float) ($property->getSecurityDeposit() ?? 0);
        $serviceFee = round($nightsAmount * 0.12, 2);
        $totalAmount = round($nightsAmount + $cleaningFee + $serviceFee + $securityDeposit, 2);

        return new BookingPriceBreakdown(
            $nights,
            round($nightsAmount, 2),
            round($cleaningFee, 2),
            $serviceFee,
            round($securityDeposit, 2),
            $totalAmount,
            'EUR',
        );
    }

    private function findPriceOverrides(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('a.availableDate', 'a.priceOverride')
            ->from(PropertyAvailability::class, 'a')
            ->andWhere('a.property = :property')
            ->andWhere('a.availableDate >= :checkin')
            ->andWhere('a.availableDate < :checkout')
            ->andWhere('a.priceOverride IS NOT NULL')
            ->setParameter('property', $property)
            ->setParameter('checkin', $checkin)
            ->setParameter('checkout', $checkout)
            ->getQuery()
            ->getArrayResult();

        $overrides = [];
        foreach ($rows as $row) {
            if (!$row['availableDate'] instanceof \DateTimeInterface) {
                continue;
            }

            $overrides[$row['availableDate']->format('Y-m-d')] = (float) $row['priceOverride'];
        }

        return $overrides;
    }
}
