<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationLifecycleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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

    #[Route('/{id}/confirm', name: 'app_reservation_confirm', methods: ['POST'])]
    #[IsGranted(ReservationVoter::CONFIRM, subject: 'reservation')]
    public function confirm(Request $request, Reservation $reservation, ReservationLifecycleService $lifecycle): Response
    {
        if (!$this->isCsrfTokenValid('reservation_confirm' . $reservation->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $lifecycle->confirm($reservation, $user);
            $this->addFlash('success', 'Réservation confirmée.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
    }

    #[Route('/{id}/cancel', name: 'app_reservation_cancel', methods: ['POST'])]
    #[IsGranted(ReservationVoter::CANCEL, subject: 'reservation')]
    public function cancel(Request $request, Reservation $reservation, ReservationLifecycleService $lifecycle): Response
    {
        if (!$this->isCsrfTokenValid('reservation_cancel' . $reservation->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reason = $request->getPayload()->getString('cancellation_reason');

        try {
            $lifecycle->cancel($reservation, $user, $reason !== '' ? $reason : null);
            $this->addFlash('success', 'Réservation annulée.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
    }
}
