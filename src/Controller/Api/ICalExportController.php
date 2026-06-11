<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ICalExportController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'api_property_ical_export', methods: ['GET'])]
    public function export(
        Property $property,
        Request $request,
        ReservationRepository $reservationRepository,
    ): Response {
        $token = $request->query->get('token');

        if ($token === null || $token !== $property->getCalendarToken()) {
            return new Response('Accès non autorisé.', Response::HTTP_UNAUTHORIZED, [
                'Content-Type' => 'text/plain',
            ]);
        }

        $reservations = $reservationRepository->createQueryBuilder('r')
            ->leftJoin('r.guest', 'g')
            ->leftJoin('g.profile', 'gp')
            ->andWhere('r.property = :property')
            ->andWhere('r.status = :status')
            ->setParameter('property', $property)
            ->setParameter('status', 'confirmed')
            ->getQuery()
            ->getResult();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($reservations as $reservation) {
            $nights = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;
            $guestName = trim(
                ($reservation->getGuest()?->getProfile()?->getFirstName() ?? '') . ' ' .
                ($reservation->getGuest()?->getProfile()?->getLastName() ?? '')
            ) ?: $reservation->getGuest()?->getEmail() ?? 'Voyageur';

            $description = sprintf(
                'Séjour %d nuit%s — %s%s — %s',
                $nights,
                $nights > 1 ? 's' : '',
                $reservation->getTotalPrice(),
                $reservation->getCurrency() === 'EUR' ? '€' : $reservation->getCurrency(),
                $reservation->getGuest()?->getEmail() ?? ''
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

        return new Response(
            implode("\r\n", $lines) . "\r\n",
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="calendar-' . $property->getId() . '.ics"',
            ]
        );
    }

    private function escapeIcal(string $value): string
    {
        return str_replace(["\r\n", "\n", "\r", ',', ';', '\\'], ['\\n', '\\n', '\\n', '\\,', '\\;', '\\\\'], $value);
    }
}
