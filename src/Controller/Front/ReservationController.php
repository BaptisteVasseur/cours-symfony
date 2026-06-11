<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\ReservationVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

    // ── Modération hôte — doit être déclaré AVANT /{id} pour éviter le conflit de routing ──

    #[Route('/demandes', name: 'app_reservation_pending', methods: ['GET'])]
    public function pending(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/reservation/pending.html.twig', [
            'reservations' => $reservationRepository->findPendingByHost($user),
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function show(Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;

        return $this->render('front/reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_reservation_accept', methods: ['POST'])]
    public function accept(Reservation $reservation, ReservationService $reservationService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getProperty()?->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette demande ne peut plus être acceptée.');

            return $this->redirectToRoute('app_reservation_pending');
        }

        $reservationService->confirm($reservation, $user);
        $this->addFlash('success', 'Réservation confirmée. Le voyageur a été notifié.');

        return $this->redirectToRoute('app_reservation_pending');
    }

    #[Route('/{id}/refuser', name: 'app_reservation_refuse', methods: ['POST'])]
    public function refuse(Reservation $reservation, Request $request, ReservationService $reservationService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getProperty()?->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif de refus est obligatoire.');

            return $this->redirectToRoute('app_reservation_pending');
        }

        $reservationService->refuse($reservation, $user, $reason);
        $this->addFlash('success', 'Demande refusée. Le voyageur a été notifié.');

        return $this->redirectToRoute('app_reservation_pending');
    }

    // ── Annulation ───────────────────────────────────────────────────────────

    #[Route('/{id}/annuler', name: 'app_reservation_cancel', methods: ['POST'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function cancel(Reservation $reservation, Request $request, ReservationService $reservationService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif d\'annulation est obligatoire.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $reservationService->cancel($reservation, $user, $reason);
        $this->addFlash('success', 'Réservation annulée.');

        return $this->redirectToRoute('app_reservation_index');
    }
}
