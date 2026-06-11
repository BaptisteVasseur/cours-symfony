<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Listing;
use App\Service\AvailabilityService;
use App\Service\PricingService;
use App\ValueObject\DateRange;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class QuoteApiController extends AbstractController
{
    #[Route('/api/listings/{id}/quote', name: 'app_api_quote', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function quote(
        Listing $listing,
        Request $request,
        PricingService $pricing,
        AvailabilityService $availability,
    ): JsonResponse {
        $checkIn = $request->query->get('checkin');
        $checkOut = $request->query->get('checkout');
        $guests = max(1, (int) $request->query->get('guests', 1));

        if (!is_string($checkIn) || !is_string($checkOut) || $checkIn === '' || $checkOut === '') {
            return $this->json(['error' => 'Dates manquantes.'], 400);
        }

        try {
            $range = DateRange::fromStrings($checkIn, $checkOut);
        } catch (\Exception) {
            return $this->json(['error' => 'Dates invalides.'], 422);
        }

        $available = $availability->isAvailable($listing, $range, $guests);
        $quote = $pricing->quote($listing, $range);

        return $this->json([
            'available' => $available,
            'reason' => $available ? null : $availability->unavailableReason($listing, $range, $guests),
            'quote' => $quote->toArray(),
        ]);
    }
}
