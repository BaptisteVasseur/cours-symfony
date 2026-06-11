<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Security\Voter\BookingVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservations')]
final class ReservationController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->findByGuestOrderedByDate($this->getUser());

        $byYear = [];
        foreach ($reservations as $reservation) {
            $year = $reservation->getCheckinDate()?->format('Y') ?? 'Inconnue';
            $byYear[$year][] = $reservation;
        }
        krsort($byYear);

        return $this->render('home/reservations.html.twig', [
            'reservationsByYear' => $byYear,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        $this->denyAccessUnlessGranted(BookingVoter::VIEW, $reservation);

        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;

        return $this->render('home/reservation.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}
