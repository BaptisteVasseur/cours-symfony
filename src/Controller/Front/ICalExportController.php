<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ICalExportController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_calendar_export', methods: ['GET'])]
    public function __invoke(
        Property $property,
        Request $request,
        ReservationRepository $reservationRepository,
    ): Response {
        $token = (string) $request->query->get('token', '');
        if ($token === '' || !hash_equals((string) $property->getCalendarExportToken(), $token)) {
            throw $this->createAccessDeniedException('Token iCal invalide.');
        }

        $reservations = $reservationRepository->findConfirmedForICal($property);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($reservations as $reservation) {
            $guestProfile = $reservation->getGuest()?->getProfile();
            $guestName = trim(sprintf(
                '%s %s',
                $guestProfile?->getFirstName() ?? '',
                $guestProfile?->getLastName() ?? '',
            ));
            $guestName = $guestName !== '' ? $guestName : (string) $reservation->getGuest()?->getEmail();

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = sprintf('UID:res-%s@clone-airbnb.local', $reservation->getId());
            $lines[] = sprintf('DTSTAMP:%s', (new \DateTimeImmutable())->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'));
            $lines[] = sprintf('DTSTART;VALUE=DATE:%s', $reservation->getCheckinDate()?->format('Ymd'));
            $lines[] = sprintf('DTEND;VALUE=DATE:%s', $reservation->getCheckoutDate()?->format('Ymd'));
            $lines[] = 'SUMMARY:' . $this->escapeIcsText(sprintf('%s - %s', $property->getTitle(), $guestName));
            $lines[] = 'DESCRIPTION:' . $this->escapeIcsText(sprintf(
                'Sejour %d nuits - %s %s - %s',
                $reservation->getCheckinDate()?->diff($reservation->getCheckoutDate() ?? $reservation->getCheckinDate())->days ?? 0,
                number_format((float) $reservation->getTotalPrice(), 2, ',', ' '),
                $reservation->getCurrency() ?? 'EUR',
                $reservation->getGuest()?->getEmail() ?? '',
            ));
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return new Response(implode("\r\n", $lines) . "\r\n", Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
        ]);
    }

    private function escapeIcsText(string $value): string
    {
        return str_replace(
            ['\\', ',', ';', "\n", "\r"],
            ['\\\\', '\,', '\;', '\n', ''],
            $value,
        );
    }
}
