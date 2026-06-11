<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ICalController extends AbstractController
{
    #[Route('/api/hosts/{id}/calendar.ics', name: 'app_host_ical_export', methods: ['GET'])]
    public function exportHost(
        User $host,
        string $id,
        ReservationRepository $reservationRepository,
        Request $request,
    ): Response {
        $token = (string) $request->query->get('token', '');
        $hostToken = $host->getHostIcalToken();

        if ($token === '' || !$hostToken || !hash_equals($hostToken, $token)) {
            throw $this->createAccessDeniedException('Invalid or missing iCal token.');
        }

        $reservations = $reservationRepository->findConfirmedByHost($host);
        $events = $this->buildEvents($reservations);
        $calendarName = 'Calendrier hote';

        $hostName = $host->getProfile()?->getFirstName();
        if ($hostName) {
            $calendarName = sprintf('Calendrier %s', $hostName);
        }

        $ical = $this->generateICalContent($calendarName, $events);

        return new Response($ical, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="host-%s.ics"', $id),
        ]);
    }

    #[Route('/api/properties/{id}/calendar.ics', name: 'app_ical_export', methods: ['GET'])]
    public function export(
        Property $property,
        string $id,
        ReservationRepository $reservationRepository,
        Request $request,
    ): Response {
        $token = (string) $request->query->get('token', '');
        $propertyToken = $property->getIcalToken();

        if ($token === '' || !$propertyToken || !hash_equals($propertyToken, $token)) {
            throw $this->createAccessDeniedException('Invalid or missing iCal token.');
        }

        $reservations = $reservationRepository->findBy([
            'property' => $property,
            'status' => 'confirmed',
        ]);

        $events = $this->buildEvents($reservations);
        $ical = $this->generateICalContent($property->getTitle(), $events);

        return new Response($ical, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $property->getId() . '.ics"',
        ]);
    }

    private function generateICalContent(string $calendarName, array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeICalText($calendarName),
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

    private function buildEvents(array $reservations): array
    {
        $events = [];
        $domain = (string) $this->getParameter('app.domain');

        foreach ($reservations as $reservation) {
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            $guest = $reservation->getGuest();
            $property = $reservation->getProperty();

            $guestName = $guest?->getProfile()?->getFirstName() ?? 'Guest';
            $hostName = $property?->getHost()?->getProfile()?->getFirstName() ?? 'N/A';
            $propertyTitle = $property?->getTitle() ?? 'Logement';

            $events[] = [
                'uid' => sprintf('%s@%s', $reservation->getId(), $domain),
                'summary' => sprintf('%s - %s (%s voyageurs)', $propertyTitle, $guestName, $reservation->getGuestsCount()),
                'description' => sprintf(
                    "Séjour sur %s\nVoyageur: %s\nContact: %s / %s\nHôte: %s\nPrix: %.2f€",
                    $propertyTitle,
                    $guestName,
                    $guest?->getEmail() ?? 'N/A',
                    $guest?->getPhone() ?? 'N/A',
                    $hostName,
                    $reservation->getTotalPrice()
                ),
                'dtstart' => $checkin->format('Ymd'),
                'dtend' => $checkout->format('Ymd'),
                'created' => $reservation->getCreatedAt()->format('Ymd\\THis\\Z'),
            ];
        }

        return $events;
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
