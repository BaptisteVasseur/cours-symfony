<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ICalExportController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_ical_export', methods: ['GET'])]
    public function __invoke(
        Request $request,
        Property $property,
        ReservationRepository $reservationRepository,
    ): Response {
        $token = $request->query->get('token', '');

        if ($token === '' || $property->getIcalExportToken() === null || !hash_equals($property->getIcalExportToken(), $token)) {
            throw new AccessDeniedHttpException('Token iCal invalide.');
        }

        $reservations = $reservationRepository->findForICalExport($property);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeIcal($property->getTitle() ?? 'Logement'),
        ];

        foreach ($reservations as $reservation) {
            $guest = $reservation->getGuest();
            $profile = $guest?->getProfile();
            $guestName = trim(($profile?->getFirstName() ?? '') . ' ' . ($profile?->getLastName() ?? ''));
            if ($guestName === '') {
                $guestName = $guest?->getEmail() ?? 'Voyageur';
            }

            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            $nights = $checkin !== null && $checkout !== null ? (int) $checkin->diff($checkout)->days : 0;

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@clone-airbnb.local';
            $lines[] = 'SUMMARY:' . $this->escapeIcal(($property->getTitle() ?? 'Logement') . ' — ' . $guestName);
            $lines[] = 'DTSTART;VALUE=DATE:' . ($checkin?->format('Ymd') ?? '');
            $lines[] = 'DTEND;VALUE=DATE:' . ($checkout?->format('Ymd') ?? '');
            $lines[] = 'DESCRIPTION:' . $this->escapeIcal(
                sprintf('Séjour %d nuit%s — %s€ — %s', $nights, $nights > 1 ? 's' : '', $reservation->getTotalPrice() ?? '0', $guest?->getEmail() ?? '')
            );
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $content = implode("\r\n", $lines) . "\r\n";

        return new Response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="calendar.ics"',
        ]);
    }

    private function escapeIcal(string $text): string
    {
        return str_replace(
            ['\\', "\n", "\r", ';', ','],
            ['\\\\', '\\n', '', '\\;', '\\,'],
            $text,
        );
    }
}
