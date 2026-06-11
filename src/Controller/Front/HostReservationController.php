<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/demandes')]
#[IsGranted('ROLE_USER')]
final class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_reservations', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservations.html.twig', [
            'pending' => $reservationRepository->findPendingForHost($user),
            'confirmed' => $reservationRepository->findConfirmedForHost($user),
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(Reservation $reservation, Request $request, ReservationWorkflow $workflow): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('accept-' . $reservation->getId(), $request->request->getString('_token'))) {
            return $this->redirectToRoute('app_host_reservations');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette demande a déjà été traitée.');
        } else {
            $workflow->accept($reservation, $user);
            $this->addFlash('success', 'Demande acceptée : la réservation est confirmée.');
        }

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_refuse', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function refuse(Reservation $reservation, Request $request, ReservationWorkflow $workflow): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('refuse-' . $reservation->getId(), $request->request->getString('_token'))) {
            return $this->redirectToRoute('app_host_reservations');
        }

        $reason = trim($request->request->getString('reason'));

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette demande a déjà été traitée.');
        } elseif (mb_strlen($reason) < 10) {
            $this->addFlash('error', 'Le motif de refus doit contenir au moins 10 caractères.');
        } else {
            $workflow->cancel($reservation, $user, $reason);
            $this->addFlash('success', 'Demande refusée.');
        }

        return $this->redirectToRoute('app_host_reservations');
    }
}
