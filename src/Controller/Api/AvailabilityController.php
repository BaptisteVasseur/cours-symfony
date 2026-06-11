<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Service\AvailabilityService;
use App\Service\BookingPriceCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AvailabilityController extends AbstractController
{
    #[Route('/api/properties/{id}/availability', name: 'app_api_availability', methods: ['GET'])]
    public function getAvailability(
        Request $request,
        Property $property,
        AvailabilityService $availabilityService,
        BookingPriceCalculator $priceCalculator,
    ): JsonResponse {
        if ($property->getStatus() !== 'published') {
            return new JsonResponse([
                'available' => false,
                'error' => 'Ce logement n\'est pas publié.',
            ]);
        }

        $checkinStr = $request->query->get('checkin');
        $checkoutStr = $request->query->get('checkout');
        $guests = $request->query->getInt('guests', 1);

        if (!$checkinStr || !$checkoutStr) {
            return new JsonResponse([
                'available' => false,
                'error' => 'Veuillez sélectionner vos dates d’arrivée et de départ.',
            ]);
        }

        try {
            $checkin = new \DateTimeImmutable($checkinStr);
            $checkout = new \DateTimeImmutable($checkoutStr);
        } catch (\Exception) {
            return new JsonResponse([
                'available' => false,
                'error' => 'Format de date invalide.',
            ]);
        }

        $checkin = $checkin->setTime(0, 0);
        $checkout = $checkout->setTime(0, 0);

        if ($checkin >= $checkout) {
            return new JsonResponse([
                'available' => false,
                'error' => 'La date de départ doit être postérieure à la date d’arrivée.',
            ]);
        }

        $today = new \DateTimeImmutable('today');
        if ($checkin < $today) {
            return new JsonResponse([
                'available' => false,
                'error' => 'La date d’arrivée doit être dans le futur.',
            ]);
        }

        if ($guests < 1) {
            return new JsonResponse([
                'available' => false,
                'error' => 'Il doit y avoir au moins 1 voyageur.',
            ]);
        }

        $maxGuests = $property->getMaxGuests() ?? 0;
        if ($guests > $maxGuests) {
            return new JsonResponse([
                'available' => false,
                'error' => sprintf('Le nombre de voyageurs dépasse la capacité maximale de %d.', $maxGuests),
            ]);
        }

        $nights = (int) $checkin->diff($checkout)->days;
        $minStay = $property->getMinStayNights();
        if ($minStay !== null && $minStay > 0 && $nights < $minStay) {
            return new JsonResponse([
                'available' => false,
                'error' => sprintf('Ce logement impose un séjour minimum de %d nuit%s.', $minStay, $minStay > 1 ? 's' : ''),
            ]);
        }

        $user = $this->getUser();
        if ($user && $property->getHost()?->getId() === $user->getId()) {
            return new JsonResponse([
                'available' => false,
                'error' => 'Vous ne pouvez pas réserver votre propre logement.',
            ]);
        }

        if (!$availabilityService->isAvailable($property, $checkin, $checkout, $guests)) {
            return new JsonResponse([
                'available' => false,
                'error' => 'Ces dates ne sont pas disponibles.',
            ]);
        }

        $breakdown = $priceCalculator->calculateBreakdown($property, $checkin, $checkout);

        return new JsonResponse([
            'available' => true,
            'nights' => $breakdown['nights'],
            'subtotal' => $breakdown['subtotal'],
            'cleaningFee' => $breakdown['cleaningFee'],
            'serviceFee' => $breakdown['serviceFee'],
            'totalPrice' => $breakdown['totalPrice'],
        ]);
    }
}
