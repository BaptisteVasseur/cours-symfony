<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\Voter\ReservationVoter;

#[Route('/host/reservations')]
#[IsGranted('ROLE_USER')]
final class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // On récupère les réservations pour les logements dont l'utilisateur est l'hôte
        $reservations = $reservationRepository->createQueryBuilder('r')
            ->join('r.property', 'p')
            ->where('p.host = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('front/host/reservation_index.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/{id}/accept', name: 'app_host_reservation_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(
        Request $request,
        Reservation $reservation,
        BookingService $bookingService
    ): Response {
        if (!$this->isCsrfTokenValid('accept' . $reservation->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('app_host_reservation_index');
        }

        $bookingService->accept($reservation);
        $this->addFlash('success', 'Réservation acceptée.');

        return $this->redirectToRoute('app_host_reservation_index');
    }

    #[Route('/{id}/reject', name: 'app_host_reservation_reject', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function reject(
        Request $request,
        Reservation $reservation,
        BookingService $bookingService
    ): Response {
        if (!$this->isCsrfTokenValid('reject' . $reservation->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('app_host_reservation_index');
        }

        $reason = $request->request->get('reason', 'Refusée par l\'hôte.');
        $bookingService->reject($reservation, (string) $reason);
        $this->addFlash('warning', 'Réservation refusée.');

        return $this->redirectToRoute('app_host_reservation_index');
    }
}
