<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'api_property_ical', methods: ['GET'])]
    public function export(
        string $id,
        Request $request,
        PropertyRepository $propertyRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $property = $propertyRepository->find($id);
        if ($property === null) {
            throw $this->createNotFoundException();
        }

        $token = $request->query->getString('token');
        if ($token === '' || $token !== $property->getIcalToken()) {
            throw $this->createAccessDeniedException('Token iCal invalide.');
        }

        $reservations = $reservationRepository->findConfirmedForIcal($id);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($reservations as $reservation) {
            $guestProfile = $reservation->getGuest()?->getProfile();
            $guestName = $guestProfile
                ? trim(($guestProfile->getFirstName() ?? '') . ' ' . ($guestProfile->getLastName() ?? ''))
                : ($reservation->getGuest()?->getEmail() ?? 'Voyageur');

            $nights = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@clone-airbnb.local';
            $lines[] = 'SUMMARY:' . $this->escapeIcal($property->getTitle() . ' — ' . $guestName);
            $lines[] = 'DTSTART;VALUE=DATE:' . $reservation->getCheckinDate()->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $reservation->getCheckoutDate()->format('Ymd');
            $lines[] = 'DESCRIPTION:' . $this->escapeIcal(
                sprintf(
                    'Séjour %d nuit%s — %s€ — %s',
                    $nights,
                    $nights > 1 ? 's' : '',
                    $reservation->getTotalPrice(),
                    $reservation->getGuest()?->getEmail() ?? '',
                )
            );
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $content = implode("\r\n", $lines) . "\r\n";

        return new Response($content, Response::HTTP_OK, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar.ics"',
        ]);
    }

    private function escapeIcal(string $value): string
    {
        return str_replace([',', ';', '\\'], ['\\,', '\\;', '\\\\'], $value);
    }
}
