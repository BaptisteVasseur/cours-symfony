<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PropertyAvailabilityManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PropertyAvailabilityRepository $availabilityRepository,
    ) {
    }

    public function blockPeriod(
        Property $property,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?float $priceOverride,
        ?int $minimumStay,
    ): int {
        if ($startDate < new \DateTimeImmutable('today')) {
            throw new \DomainException('La date de début ne peut pas être passée.');
        }

        if ($endDate <= $startDate) {
            throw new \DomainException('La date de fin doit être postérieure à la date de début.');
        }

        $blocked = 0;
        $cursor = $startDate;

        while ($cursor < $endDate) {
            $availability = $this->availabilityRepository->findOneForPropertyDate($property, $cursor);
            if ($availability === null) {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($cursor);
                $this->entityManager->persist($availability);
            }

            $availability->setIsAvailable(false);
            $availability->setPriceOverride($priceOverride !== null ? number_format($priceOverride, 2, '.', '') : null);
            $availability->setMinimumStay($minimumStay);

            ++$blocked;
            $cursor = $cursor->modify('+1 day');
        }

        $this->entityManager->flush();

        return $blocked;
    }

    public function unblock(PropertyAvailability $availability): void
    {
        $this->entityManager->remove($availability);
        $this->entityManager->flush();
    }
}
