<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_ical_export', methods: ['GET'])]
    public function export(
        Property $property,
        string $id,
        ReservationRepository $reservationRepository,
        Request $request,
    ): Response {
        // Verify token from query parameter
        $token = $request->query->get('token');
        if (!$token || $token !== $property->getIcalToken()) {
            throw $this->createAccessDeniedException('Invalid or missing iCal token.');
        }

        // Get confirmed reservations for this property
        $reservations = $reservationRepository->findBy([
            'property' => $property,
            'status' => 'confirmed',
        ]);

        // Generate iCal format
        $events = [];
        foreach ($reservations as $reservation) {
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            $guest = $reservation->getGuest();

            $uid = $reservation->getId() . '@' . $this->getParameter('app.domain');
            $summary = sprintf(
                'Réservation : %s (%s voyageurs)',
                $guest?->getFirstname() ?? 'Guest',
                $reservation->getGuestsCount()
            );

            $events[] = [
                'uid' => $uid,
                'summary' => $summary,
                'description' => sprintf(
                    "Réservation de %s à %s\nHôte: %s\nPrix: %.2f€",
                    $guest?->getEmail() ?? 'N/A',
                    $guest?->getPhone() ?? 'N/A',
                    $property->getHost()?->getFirstname() ?? 'N/A',
                    $reservation->getTotalPrice()
                ),
                'dtstart' => $checkin->format('Ymd'),
                'dtend' => $checkout->format('Ymd'),
                'created' => $reservation->getCreatedAt()->format('Ymd\\THis\\Z'),
            ];
        }

        $ical = $this->generateICalContent($property, $events);

        return new Response($ical, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $property->getId() . '.ics"',
        ]);
    }

    private function generateICalContent(Property $property, array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $property->getTitle(),
            'X-WR-TIMEZONE:Europe/Paris',
            'BEGIN:VTIMEZONE',
            'TZID:Europe/Paris',
            'BEGIN:DAYLIGHT',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'TZNAME:CEST',
            'DTSTART:19700329T020000',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'TZNAME:CET',
            'DTSTART:19701025T030000',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];

        // Add events
        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $event['uid'];
            $lines[] = 'DTSTAMP:' . $event['created'];
            $lines[] = 'DTSTART;VALUE=DATE:' . $event['dtstart'];
            $lines[] = 'DTEND;VALUE=DATE:' . $event['dtend'];
            $lines[] = 'SUMMARY:' . $this->escapeICalText($event['summary']);
            $lines[] = 'DESCRIPTION:' . $this->escapeICalText($event['description']);
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    private function escapeICalText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(',', '\\,', $text);
        $text = str_replace(';', '\\;', $text);
        $text = str_replace("\n", '\\n', $text);
        $text = str_replace("\r", '', $text);

        return $text;
    }
}
