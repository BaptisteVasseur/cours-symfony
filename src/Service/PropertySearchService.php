<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Exception\BookingException;
use App\Repository\PropertyRepository;
use App\Service\Booking\PropertyAvailabilityService;

final class PropertySearchService
{
    public function __construct(
        private PropertyRepository $propertyRepository,
        private PropertyAvailabilityService $propertyAvailabilityService,
    ) {
    }

    /**
     * @return list<Property>
     */
    public function search(
        ?string $destination,
        ?\DateTimeImmutable $checkin,
        ?\DateTimeImmutable $checkout,
        int $guests,
    ): array {
        $properties = $this->propertyRepository->findPublishedForSearch($destination, $guests);

        if ($checkin === null || $checkout === null) {
            return $properties;
        }

        return array_values(array_filter(
            $properties,
            fn (Property $property): bool => $this->isAvailableForSearch($property, $checkin, $checkout, $guests),
        ));
    }

    private function isAvailableForSearch(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
    ): bool {
        try {
            $this->propertyAvailabilityService->assertBookable($property, $checkin, $checkout, $guests);

            return true;
        } catch (BookingException) {
            return false;
        }
    }
}
