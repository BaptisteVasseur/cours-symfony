<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Reservation;
use App\Entity\User;
use App\Exception\InvalidReservationTransitionException;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\Reservation\ReservationStateManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace hôte : tableau de bord de modération des demandes de réservation (§B.2).
 */
#[Route('/compte/demandes')]
#[IsGranted('ROLE_HOST')]
final class ReservationModerationController extends AbstractController
{
    #[Route('', name: 'app_host_reservations', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('host/reservation/index.html.twig', [
            'pending' => $reservationRepository->findPendingForHost($user),
            'reservations' => $reservationRepository->findByHostForListing($user),
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(Reservation $reservation, Request $request, ReservationStateManager $stateManager): Response
    {
        if (!$this->isCsrfTokenValid('moderate' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $stateManager->confirm($reservation, $this->getUser() instanceof User ? $this->getUser() : null);
            $this->addFlash('success', 'La demande de réservation a été acceptée.');
        } catch (InvalidReservationTransitionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_reject', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function reject(Reservation $reservation, Request $request, ReservationStateManager $stateManager): Response
    {
        if (!$this->isCsrfTokenValid('moderate' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $reason = trim((string) $request->request->get('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif est obligatoire pour refuser une demande.');

            return $this->redirectToRoute('app_host_reservations');
        }

        try {
            $stateManager->reject($reservation, $reason, $this->getUser() instanceof User ? $this->getUser() : null);
            $this->addFlash('success', 'La demande de réservation a été refusée.');
        } catch (InvalidReservationTransitionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservations');
    }
}
