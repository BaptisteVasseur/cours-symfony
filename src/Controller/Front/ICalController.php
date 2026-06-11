<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\ReservationVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservations')]
#[IsGranted('ROLE_USER')]
final class ICalController extends AbstractController
{
    #[Route('/{id}/ical', name: 'app_reservation_ical', methods: ['GET'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function download(
        Reservation $reservation,
        ReservationRepository $reservationRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;

        $property = $reservation->getProperty();
        $propertyTitle = $property?->getTitle() ?? 'Logement';
        $address = $property?->getAddress();
        $location = $address
            ? implode(', ', array_filter([
                $address->getAddressLine1() ?? null,
                $address->getCity() ?? null,
                $address->getCountry() ?? null,
            ]))
            : '';

        $checkin = $reservation->getCheckinDate();
        $checkout = $reservation->getCheckoutDate();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $uid = sprintf('%s@airbnb.local', $reservation->getId());
        $dtstart = $checkin?->format('Ymd');
        $dtend = $checkout?->format('Ymd');
        $dtstamp = $now->format('Ymd\THis\Z');

        $summary = $this->escapeIcal("Séjour : {$propertyTitle}");
        $description = $this->escapeIcal(
            "Réservation Airbnb\n"
            . "Logement : {$propertyTitle}\n"
            . "Arrivée : " . ($checkin?->format('d/m/Y') ?? '') . "\n"
            . "Départ : " . ($checkout?->format('d/m/Y') ?? '') . "\n"
            . "Voyageurs : " . ($reservation->getGuestsCount() ?? 1) . "\n"
            . "Total : " . ($reservation->getTotalPrice() ?? '0') . ' ' . ($reservation->getCurrency() ?? 'EUR')
        );
        $locationEscaped = $this->escapeIcal($location);

        $ical = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Airbnb//Reservation//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtstamp}",
            "DTSTART;VALUE=DATE:{$dtstart}",
            "DTEND;VALUE=DATE:{$dtend}",
            "SUMMARY:{$summary}",
            "DESCRIPTION:{$description}",
            "LOCATION:{$locationEscaped}",
            'STATUS:CONFIRMED',
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        $filename = sprintf('reservation-%s.ics', substr((string) $reservation->getId(), 0, 8));

        return new Response(
            $ical,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ],
        );
    }

    private function escapeIcal(string $value): string
    {
        $value = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $value);
        $value = str_replace("\n", '\\n', $value);

        return $value;
    }
}
