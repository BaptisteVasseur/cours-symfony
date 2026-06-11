<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Exception\ReservationActionException;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\Booking\ReservationStatusManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/demandes-reservation')]
#[IsGranted('ROLE_HOST')]
final class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host_reservation/index.html.twig', [
            'reservations' => $reservationRepository->findPendingForHost($user),
        ]);
    }

    #[Route('/{id}/accept', name: 'app_host_reservation_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(
        Request $request,
        Reservation $reservation,
        ReservationStatusManager $reservationStatusManager,
    ): Response {
        if (!$this->isCsrfTokenValid('host_reservation_accept_' . $reservation->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $reservationStatusManager->confirmPending($reservation, $user);
            $this->addFlash('success', 'La reservation a ete acceptee.');
        } catch (ReservationActionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservation_index');
    }

    #[Route('/{id}/reject', name: 'app_host_reservation_reject', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function reject(
        Request $request,
        Reservation $reservation,
        ReservationStatusManager $reservationStatusManager,
    ): Response {
        if (!$this->isCsrfTokenValid('host_reservation_reject_' . $reservation->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $reservationStatusManager->rejectPending($reservation, $user, $request->request->getString('reason'));
            $this->addFlash('success', 'La demande a ete refusee.');
        } catch (ReservationActionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservation_index');
    }
}
