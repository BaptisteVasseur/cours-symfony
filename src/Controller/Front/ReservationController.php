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
use Symfony\Component\HttpFoundation\Request;

#[Route('/reservations')]
#[IsGranted('ROLE_USER')]
final class ReservationController extends AbstractController
{
    #[Route('', name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/reservation/index.html.twig', [
            'reservations' => $reservationRepository->findByGuestForListing($user),
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function show(Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;

        return $this->render('front/reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/calendar.ics', name: 'app_reservation_ical', methods: ['GET'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function ical(Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;

        if (!in_array($reservation->getStatus(), ['confirmed', 'completed'], true)) {
            throw $this->createNotFoundException('Le fichier iCal n\'est disponible que pour les réservations confirmées.');
        }

        $property     = $reservation->getProperty();
        $guest        = $reservation->getGuest();
        $guestProfile = $guest?->getProfile();
        $guestName    = trim(($guestProfile?->getFirstName() ?? '') . ' ' . ($guestProfile?->getLastName() ?? ''));
        $guestName    = $guestName ?: ($guest?->getEmail() ?? 'Voyageur');
        // Date-only diff to count nights independently of intraday check-in/out times
        $nights = (int) $reservation->getCheckinDate()->setTime(0, 0, 0)
            ->diff($reservation->getCheckoutDate()->setTime(0, 0, 0))->days;

        $title   = ($property?->getTitle() ?? 'Séjour') . ' — ' . $guestName;
        $address = '';
        if ($property?->getAddress()) {
            $address = implode(', ', array_filter([
                $property->getAddress()->getCity(),
                $property->getAddress()->getCountry(),
            ]));
        }

        $description = sprintf(
            'Séjour %d nuit%s — %s€ — Réservation #%s',
            $nights,
            $nights > 1 ? 's' : '',
            number_format((float) $reservation->getTotalPrice(), 0, ',', ' '),
            (string) $reservation->getId(),
        );

        $escape = static function (string $v): string {
            $v = str_replace('\\', '\\\\', $v);
            $v = str_replace(';', '\\;', $v);
            $v = str_replace(',', '\\,', $v);
            return str_replace("\n", '\\n', $v);
        };

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:res-' . $reservation->getId() . '@clone-airbnb.local',
            'SUMMARY:' . $escape($title),
            'DTSTART;VALUE=DATE:' . $reservation->getCheckinDate()->format('Ymd'),
            'DTEND;VALUE=DATE:' . $reservation->getCheckoutDate()->format('Ymd'),
            'DESCRIPTION:' . $escape($description),
            'LOCATION:' . $escape($address),
            'STATUS:CONFIRMED',
            'DTSTAMP:' . (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z'),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($property?->getTitle() ?? 'sejour')) ?? 'sejour';

        return new Response(
            implode("\r\n", $lines) . "\r\n",
            Response::HTTP_OK,
            [
                'Content-Type'        => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="reservation-' . trim($slug, '-') . '.ics"',
                'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            ],
        );
    }
}
