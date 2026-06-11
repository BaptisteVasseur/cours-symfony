<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/booking', name: 'api_booking_')]
final class BookingApiController extends AbstractController
{
    #[Route('/calculate-price/{id}', name: 'calculate_price', methods: ['GET'])]
    public function calculatePrice(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
    ): JsonResponse {
        $checkinStr  = $request->query->get('checkin');
        $checkoutStr = $request->query->get('checkout');
        $guests      = max(1, (int) $request->query->get('guests', 1));

        if (!$checkinStr || !$checkoutStr) {
            return $this->json(['error' => 'Paramètres checkin et checkout requis.'], 400);
        }

        $checkIn  = \DateTimeImmutable::createFromFormat('Y-m-d', $checkinStr);
        $checkOut = \DateTimeImmutable::createFromFormat('Y-m-d', $checkoutStr);

        if (!$checkIn || !$checkOut || $checkOut <= $checkIn) {
            return $this->json(['error' => 'Dates invalides.'], 400);
        }

        // Vérifier la capacité
        if ($property->getMaxGuests() !== null && $guests > $property->getMaxGuests()) {
            return $this->json([
                'available' => false,
                'reason'    => 'max_guests',
                'message'   => sprintf('Ce logement accepte %d voyageur(s) maximum.', $property->getMaxGuests()),
            ]);
        }

        // Vérifier les dates bloquées (PropertyAvailability)
        $blocked = $availabilityRepository->findBlockedInPeriod($property, $checkIn, $checkOut);
        if (count($blocked) > 0) {
            return $this->json([
                'available' => false,
                'reason'    => 'unavailable_dates',
                'message'   => 'Certaines dates sélectionnées ne sont pas disponibles.',
            ]);
        }

        // Vérifier les réservations confirmées qui chevauchent
        $overlapping = $reservationRepository->findByPropertyAndPeriod($property, $checkIn, $checkOut);
        foreach ($overlapping as $res) {
            if ($res->getStatus() === 'confirmed') {
                return $this->json([
                    'available' => false,
                    'reason'    => 'already_booked',
                    'message'   => 'Ces dates sont déjà réservées.',
                ]);
            }
        }

        $nights           = (int) $checkIn->diff($checkOut)->days;
        $defaultPrice     = (float) ($property->getPricePerNight() ?? 0);
        $cleaning         = (float) ($property->getCleaningFee() ?? 0);

        // Récupérer les overrides de prix sur la période (1 requête)
        $overrides = $availabilityRepository->getPriceOverridesInPeriod($property, $checkIn, $checkOut);

        // Calculer le prix nuit par nuit en appliquant le tarif spécial si présent
        $base       = 0.0;
        $nightLines = []; // pour détailler si besoin plus tard
        for ($i = 0; $i < $nights; $i++) {
            $night     = $checkIn->modify("+{$i} days");
            $key       = $night->format('Y-m-d');
            $nightRate = $overrides[$key] ?? $defaultPrice;
            $base     += $nightRate;
            $nightLines[] = ['date' => $key, 'price' => $nightRate, 'special' => isset($overrides[$key])];
        }

        $serviceFee     = round($base * 0.05, 2);
        $total          = round($base + $cleaning + $serviceFee, 2);
        $hasSpecialRate = count($overrides) > 0;

        return $this->json([
            'available'      => true,
            'nights'         => $nights,
            'pricePerNight'  => $hasSpecialRate ? round($base / max($nights, 1), 2) : $defaultPrice,
            'hasSpecialRate' => $hasSpecialRate,
            'cleaning'       => $cleaning,
            'serviceFee'     => $serviceFee,
            'total'          => $total,
            'baseTotal'      => $base,
            'currency'       => 'EUR',
        ]);
    }
}
