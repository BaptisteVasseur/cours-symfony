<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_api_property_ical', methods: ['GET'])]
    public function export(Property $property, Request $request, ReservationRepository $reservationRepository): Response
    {
        $token = $request->query->get('token');

        if ($token === null || $property->getIcalToken() === null || $token !== $property->getIcalToken()) {
            return new Response('Accès refusé.', Response::HTTP_FORBIDDEN);
        }

        $reservations = $reservationRepository->findConfirmedForProperty($property);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//StayBook//StayBook Calendar//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($reservations as $reservation) {
            $guest = $reservation->getGuest();
            $profile = $guest?->getProfile();
            $firstName = $profile?->getFirstName() ?? '';
            $lastName = $profile?->getLastName() ?? '';

            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();

            $dtstart = $checkin?->format('Ymd') ?? '';
            $dtend = $checkout?->format('Ymd') ?? '';

            $checkinFr = $checkin?->format('d/m/Y') ?? '';
            $checkoutFr = $checkout?->format('d/m/Y') ?? '';

            $description = sprintf(
                'Séjour du %s au %s\n%d voyageur(s)\nTotal : %s €',
                $checkinFr,
                $checkoutFr,
                $reservation->getGuestsCount() ?? 0,
                $reservation->getTotalPrice() ?? '0',
            );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $reservation->getId() . '@staybook';
            $lines[] = 'SUMMARY:' . $property->getTitle() . ' - ' . $firstName . ' ' . $lastName;
            $lines[] = 'DTSTART;VALUE=DATE:' . $dtstart;
            $lines[] = 'DTEND;VALUE=DATE:' . $dtend;
            $lines[] = 'DESCRIPTION:' . $description;
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $ics = implode("\r\n", $lines) . "\r\n";

        return new Response($ics, Response::HTTP_OK, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="calendar.ics"',
        ]);
    }
}
