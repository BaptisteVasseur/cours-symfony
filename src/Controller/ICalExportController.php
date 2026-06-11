<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ICalExportController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_ical_export', methods: ['GET'])]
    public function export(
        string $id,
        Request $request,
        PropertyRepository $propertyRepository,
    ): Response {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Logement introuvable.');
        }

        $property = $propertyRepository->find($id);
        if ($property === null) {
            throw $this->createNotFoundException('Logement introuvable.');
        }

        $token = $request->query->getString('token');
        if ($token === '' || $property->getIcalToken() === null || !hash_equals($property->getIcalToken(), $token)) {
            throw $this->createAccessDeniedException('Token invalide.');
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeIcal($property->getTitle() ?? ''),
        ];

        foreach ($property->getReservations() as $reservation) {
            if ($reservation->getStatus() !== 'confirmed') {
                continue;
            }

            $guest = $reservation->getGuest();
            $guestName = $guest?->getProfile()?->getFirstName() . ' ' . $guest?->getProfile()?->getLastName();
            $guestName = trim($guestName) !== '' ? trim($guestName) : ($guest?->getEmail() ?? 'Voyageur');

            $nights = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;
            $description = sprintf(
                'Séjour %d nuit%s — %s%s — %s',
                $nights,
                $nights > 1 ? 's' : '',
                $reservation->getTotalPrice(),
                $reservation->getCurrency(),
                $guest?->getEmail() ?? '',
            );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@clone-airbnb.local';
            $lines[] = 'SUMMARY:' . $this->escapeIcal($property->getTitle() . ' — ' . $guestName);
            $lines[] = 'DTSTART;VALUE=DATE:' . $reservation->getCheckinDate()->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $reservation->getCheckoutDate()->format('Ymd');
            $lines[] = 'DESCRIPTION:' . $this->escapeIcal($description);
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $content = implode("\r\n", $lines) . "\r\n";

        return new Response($content, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="calendar.ics"',
        ]);
    }

    private function escapeIcal(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
    }
}