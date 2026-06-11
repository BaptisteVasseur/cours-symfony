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
use App\Service\ReservationStatusManager;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

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
    
    #[Route('/{id}/annuler', name: 'app_reservation_cancel', methods: ['POST'])]
    public function cancel(
        #[CurrentUser] User $user,
        Reservation $reservation,
        Request $request,
        ReservationStatusManager $statusManager,
    ): Response {
        // Autorisation : le voyageur OU l'hôte du logement
        $isGuest = $reservation->getGuest() === $user;
        $isHost = $reservation->getProperty()?->getHost() === $user;
        if (!$isGuest && !$isHost) {
            throw $this->createAccessDeniedException();
        }

        if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        if (!$this->isCsrfTokenValid('cancel' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif d\'annulation est obligatoire.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $statusManager->cancel($reservation, $user, $reason);
        $this->addFlash('success', 'Réservation annulée. Les deux parties ont été notifiées.');

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
    }
}
