<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'api_ical_export', methods: ['GET'])]
    public function export(
        Property $property,
        Request $request,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $em,
    ): Response {
        $token = $request->query->get('token');

        if ($token === null || $token === '' || $token !== $property->getIcalExportToken()) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        if ($property->getIcalExportToken() === null) {
            $property->setIcalExportToken(bin2hex(random_bytes(32)));
            $em->flush();
        }

        $reservations = $reservationRepository->findConfirmedByProperty($property);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($reservations as $reservation) {
            $guest   = $reservation->getGuest();
            $profile = $guest?->getProfile();
            $name    = $profile
                ? trim(($profile->getFirstName() ?? '') . ' ' . ($profile->getLastName() ?? ''))
                : ($guest?->getEmail() ?? 'Voyageur');

            $checkin  = $reservation->getCheckinDate()?->format('Ymd') ?? '';
            $checkout = $reservation->getCheckoutDate()?->format('Ymd') ?? '';
            $nights   = $reservation->getCheckinDate() && $reservation->getCheckoutDate()
                ? (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days
                : 0;

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@clone-airbnb.local';
            $lines[] = 'SUMMARY:' . $this->escapeIcal($property->getTitle() . ' — ' . $name);
            $lines[] = 'DTSTART;VALUE=DATE:' . $checkin;
            $lines[] = 'DTEND;VALUE=DATE:' . $checkout;
            $lines[] = 'DESCRIPTION:' . $this->escapeIcal(
                sprintf(
                    'Séjour %d nuit%s — %s€ — %s',
                    $nights,
                    $nights > 1 ? 's' : '',
                    number_format((float) $reservation->getTotalPrice(), 0, ',', ' '),
                    $guest?->getEmail() ?? ''
                )
            );
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return new Response(
            implode("\r\n", $lines) . "\r\n",
            Response::HTTP_OK,
            [
                'Content-Type'        => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="calendar.ics"',
            ]
        );
    }

    private function escapeIcal(string $value): string
    {
        return str_replace([',', ';', '\\', "\n"], ['\\,', '\\;', '\\\\', '\\n'], $value);
    }
}
