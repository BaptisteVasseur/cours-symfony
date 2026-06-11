<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Exception\PropertyAvailabilityException;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PropertyAvailabilityManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PropertyAvailabilityRepository $propertyAvailabilityRepository,
    ) {
    }

    public function applyRange(
        Property $property,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        string $mode,
        ?int $minimumStay,
        ?float $priceOverride,
    ): void {
        if ($endDate < $startDate) {
            throw new PropertyAvailabilityException('La date de fin doit etre posterieure ou egale a la date de debut.');
        }

        if (!in_array($mode, ['blocked', 'available'], true)) {
            throw new PropertyAvailabilityException('Le mode de disponibilite est invalide.');
        }

        if ($minimumStay !== null && $minimumStay < 1) {
            throw new PropertyAvailabilityException('Le sejour minimum doit etre d\'au moins 1 nuit.');
        }

        if ($priceOverride !== null && $priceOverride < 0) {
            throw new PropertyAvailabilityException('Le tarif personnalise ne peut pas etre negatif.');
        }

        $existingEntries = $this->propertyAvailabilityRepository->findForRange(
            $property,
            $startDate,
            $endDate->modify('+1 day'),
        );

        $indexedEntries = [];
        foreach ($existingEntries as $entry) {
            $date = $entry->getAvailableDate();
            if ($date === null) {
                continue;
            }

            $indexedEntries[$date->format('Y-m-d')] = $entry;
        }

        $cursor = $startDate;
        $isAvailable = $mode === 'available';

        while ($cursor <= $endDate) {
            $key = $cursor->format('Y-m-d');
            $entry = $indexedEntries[$key] ?? null;

            if ($isAvailable && $minimumStay === null && $priceOverride === null) {
                if ($entry !== null) {
                    $this->entityManager->remove($entry);
                }

                $cursor = $cursor->modify('+1 day');
                continue;
            }

            if ($entry === null) {
                $entry = new PropertyAvailability();
                $entry->setProperty($property);
                $entry->setAvailableDate($cursor);
                $this->entityManager->persist($entry);
            }

            $entry->setIsAvailable($isAvailable);
            $entry->setMinimumStay($minimumStay);
            $entry->setPriceOverride($priceOverride !== null ? number_format($priceOverride, 2, '.', '') : null);

            $cursor = $cursor->modify('+1 day');
        }

        $this->entityManager->flush();
    }
}
