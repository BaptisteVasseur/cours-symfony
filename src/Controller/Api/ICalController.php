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
    public function calendar(
        string $id,
        Request $request,
        PropertyRepository $propertyRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $property = $propertyRepository->find($id);

        if ($property === null) {
            throw $this->createNotFoundException();
        }

        $token = $request->query->get('token');
        if ($token === null || $token !== $property->getIcalToken()) {
            throw $this->createAccessDeniedException();
        }

        $reservations = $reservationRepository->findActiveForProperty($property);

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');
        $prodId = '-//StayNest//Calendar//FR';
        $calName = $property->getTitle() ?? 'StayNest';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . $prodId,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $calName,
            'X-WR-TIMEZONE:Europe/Paris',
        ];

        foreach ($reservations as $reservation) {
            $uid = $reservation->getId() . '@staynest';
            $dtstart = $reservation->getCheckinDate()->format('Ymd');
            $dtend   = $reservation->getCheckoutDate()->format('Ymd');
            $summary = sprintf('Réservation #%s', substr((string) $reservation->getId(), 0, 8));

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . $now;
            $lines[] = 'DTSTART;VALUE=DATE:' . $dtstart;
            $lines[] = 'DTEND;VALUE=DATE:' . $dtend;
            $lines[] = 'SUMMARY:' . $summary;
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
}
