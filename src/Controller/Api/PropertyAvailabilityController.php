<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Service\AvailabilityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class PropertyAvailabilityController extends AbstractController
{
    #[Route('/api/properties/{id}/blocked-dates', name: 'api_property_blocked_dates', methods: ['GET'])]
    public function blockedDates(Property $property, AvailabilityService $availabilityService): JsonResponse
    {
        $ranges = $availabilityService->getBlockedRanges($property);

        $data = array_map(static fn (array $range): array => [
            'start' => $range['start']->format('Y-m-d'),
            'end' => $range['end']->format('Y-m-d'),
            'type' => $range['type'],
            'label' => $range['label'],
        ], $ranges);

        return $this->json($data);
    }
}
