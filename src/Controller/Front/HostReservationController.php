<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\ReservationStatusManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/reservations')]
#[IsGranted('ROLE_USER')]
final class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_reservations', methods: ['GET'])]
    public function index(#[CurrentUser] User $host, ReservationRepository $reservationRepository): Response
    {
        return $this->render('front/host/reservations.html.twig', [
            'pending' => $reservationRepository->findPendingForHost($host),
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    public function accept(
        #[CurrentUser] User $host,
        Reservation $reservation,
        Request $request,
        ReservationStatusManager $statusManager,
    ): Response {
        $this->assertHostOwns($reservation, $host);
        $this->assertPending($reservation);

        if (!$this->isCsrfTokenValid('moderate' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $statusManager->confirm($reservation, $host);
        $this->addFlash('success', 'Demande acceptée. Le voyageur a été notifié.');

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_refuse', methods: ['POST'])]
    public function refuse(
        #[CurrentUser] User $host,
        Reservation $reservation,
        Request $request,
        ReservationStatusManager $statusManager,
    ): Response {
        $this->assertHostOwns($reservation, $host);
        $this->assertPending($reservation);

        if (!$this->isCsrfTokenValid('moderate' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_reservations');
        }

        $statusManager->refuse($reservation, $host, $reason);
        $this->addFlash('success', 'Demande refusée. Le voyageur a été notifié.');

        return $this->redirectToRoute('app_host_reservations');
    }

    private function assertHostOwns(Reservation $reservation, User $host): void
    {
        if ($reservation->getProperty()?->getHost() !== $host) {
            throw $this->createAccessDeniedException();
        }
    }

    private function assertPending(Reservation $reservation): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw $this->createNotFoundException('Cette demande n\'est plus en attente.');
        }
    }
}