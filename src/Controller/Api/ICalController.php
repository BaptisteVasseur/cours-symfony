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
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_ical_export', methods: ['GET'])]
    public function export(
        Property $property,
        Request $request,
        ReservationRepository $reservationRepository,
    ): Response {
        $token = $request->query->get('token', '');

        if ($token === '' || $property->getIcalToken() === null || !hash_equals($property->getIcalToken(), $token)) {
            throw $this->createNotFoundException('Token invalide ou manquant.');
        }

        $reservations = $reservationRepository->findConfirmedForIcal($property);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeText($property->getTitle() ?? 'Logement'),
            'X-WR-TIMEZONE:Europe/Paris',
        ];

        foreach ($reservations as $reservation) {
            $guest        = $reservation->getGuest();
            $guestProfile = $guest?->getProfile();
            $firstName    = $guestProfile?->getFirstName() ?? '';
            $lastName     = $guestProfile?->getLastName() ?? '';
            $guestName    = trim("$firstName $lastName") ?: ($guest?->getEmail() ?? 'Voyageur');
            $nights       = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;

            $summary     = $this->escapeText(($property->getTitle() ?? 'Logement') . ' — ' . $guestName);
            $description  = $this->escapeText(sprintf(
                'Séjour %d nuit%s — %s€ — %s',
                $nights,
                $nights > 1 ? 's' : '',
                number_format((float) $reservation->getTotalPrice(), 0, ',', ' '),
                $guest?->getEmail() ?? '',
            ));

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@clone-airbnb.local';
            $lines[] = 'SUMMARY:' . $summary;
            $lines[] = 'DTSTART;VALUE=DATE:' . $reservation->getCheckinDate()->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $reservation->getCheckoutDate()->format('Ymd');
            $lines[] = 'DESCRIPTION:' . $description;
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'DTSTAMP:' . (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $content = implode("\r\n", $lines) . "\r\n";

        return new Response($content, Response::HTTP_OK, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $this->slugify($property->getTitle() ?? 'logement') . '.ics"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    private function escapeText(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace("\n", '\\n', $value);

        return $value;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? 'logement';

        return trim($text, '-');
    }
}
