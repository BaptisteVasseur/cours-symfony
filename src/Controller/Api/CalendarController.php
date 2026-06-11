<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'api_property_calendar', methods: ['GET'])]
    public function calendar(
        Property $property,
        Request $request,
        ReservationRepository $reservationRepository,
    ): Response {
        $token = $request->query->get('token');

        if (!$token || !hash_equals((string) $property->getCalendarToken(), $token)) {
            return new Response('Accès non autorisé.', Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/plain']);
        }

        $reservations = $reservationRepository->findConfirmedAndBlocksForCalendar($property);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->foldLine($property->getTitle() ?? ''),
        ];

        foreach ($reservations as $reservation) {
            $guest = $reservation->getGuest();
            $profile = $guest?->getProfile();

            $guestName = $profile
                ? trim(($profile->getFirstName() ?? '') . ' ' . ($profile->getLastName() ?? ''))
                : ($guest?->getEmail() ?? 'Voyageur');

            if ($reservation->isBlock()) {
                $summary = ($property->getTitle() ?? 'Logement') . ' — Bloqué';
                $description = $reservation->getBlockReason() ?? 'Période bloquée par l\'hôte';
            } else {
                $summary = ($property->getTitle() ?? 'Logement') . ' — ' . $guestName;
                $nights = $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;
                $description = sprintf(
                    'Séjour %d nuit%s — %s€ — %s',
                    $nights,
                    $nights > 1 ? 's' : '',
                    number_format((float) $reservation->getTotalPrice(), 0, ',', ' '),
                    $guest?->getEmail() ?? '',
                );
            }

            $uid = sprintf('res-%s@clone-airbnb.local', $reservation->getId());
            $dtstart = $reservation->getCheckinDate()->format('Ymd');
            $dtend = $reservation->getCheckoutDate()->format('Ymd');
            $dtstamp = $reservation->getCreatedAt()->format('Ymd\THis\Z');

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . $dtstamp;
            $lines[] = 'DTSTART;VALUE=DATE:' . $dtstart;
            $lines[] = 'DTEND;VALUE=DATE:' . $dtend;
            $lines[] = $this->foldLine('SUMMARY:' . $this->escapeIcal($summary));
            $lines[] = $this->foldLine('DESCRIPTION:' . $this->escapeIcal($description));
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $content = implode("\r\n", $lines) . "\r\n";

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="calendar.ics"',
        ]);
    }

    private function escapeIcal(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $value);
    }

    private function foldLine(string $line): string
    {
        $result = '';
        while (strlen($line) > 75) {
            $result .= substr($line, 0, 75) . "\r\n ";
            $line = substr($line, 75);
        }

        return $result . $line;
    }
}
