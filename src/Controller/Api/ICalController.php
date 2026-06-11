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
    #[Route('/api/properties/{id}/calendar.ics', name: 'api_property_ical', methods: ['GET'])]
    public function export(
        Property $property,
        Request $request,
        ReservationRepository $reservationRepository,
    ): Response {
        $token = $request->query->get('token');

        if ($token === null || $token === '' || $property->getIcalToken() === null || !hash_equals($property->getIcalToken(), $token)) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED, ['Content-Type' => 'text/plain']);
        }

        $reservations = $reservationRepository->findConfirmedForIcal($property);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//StayNest//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeIcal($property->getTitle() ?? 'StayNest'),
        ];

        foreach ($reservations as $reservation) {
            $guest = $reservation->getGuest();
            $guestProfile = $guest?->getProfile();
            $guestName = $guestProfile
                ? trim($guestProfile->getFirstName() . ' ' . $guestProfile->getLastName())
                : ($guest?->getEmail() ?? 'Guest');

            $nights = $reservation->getCheckinDate() && $reservation->getCheckoutDate()
                ? (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days
                : 0;

            $description = sprintf(
                'Séjour %d nuit%s — %s€ — %s',
                $nights,
                $nights > 1 ? 's' : '',
                number_format((float) $reservation->getTotalPrice(), 2, ',', ' '),
                $guest?->getEmail() ?? '',
            );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@staynest.local';
            $lines[] = 'SUMMARY:' . $this->escapeIcal($property->getTitle() . ' — ' . $guestName);
            $lines[] = 'DTSTART;VALUE=DATE:' . ($reservation->getCheckinDate()?->format('Ymd') ?? '');
            $lines[] = 'DTEND;VALUE=DATE:' . ($reservation->getCheckoutDate()?->format('Ymd') ?? '');
            $lines[] = 'DESCRIPTION:' . $this->escapeIcal($description);
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return new Response(
            implode("\r\n", $lines) . "\r\n",
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/calendar; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="calendar.ics"',
            ],
        );
    }

    private function escapeIcal(string $value): string
    {
        return str_replace(["\r\n", "\n", "\r", ',', ';', '\\'], ['\\n', '\\n', '\\n', '\\,', '\\;', '\\\\'], $value);
    }
}
