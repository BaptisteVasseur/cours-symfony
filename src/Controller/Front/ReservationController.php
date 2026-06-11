<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\ReservationWorkflow;
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
    #[IsGranted(ReservationVoter::CANCEL, subject: 'reservation')]
    public function cancel(Reservation $reservation, Request $request, ReservationWorkflow $workflow): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $isHost = $reservation->getProperty()?->getHost()?->getId() === $user->getId();
        $redirect = $isHost
            ? $this->redirectToRoute('app_host_reservations')
            : $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);

        if (!$this->isCsrfTokenValid('cancel-' . $reservation->getId(), $request->request->getString('_token'))) {
            return $redirect;
        }

        $reason = trim($request->request->getString('reason'));

        if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');
        } elseif (mb_strlen($reason) < 10) {
            $this->addFlash('error', 'Le motif d\'annulation doit contenir au moins 10 caractères.');
        } else {
            $workflow->cancel($reservation, $user, $reason);
            $this->addFlash('success', 'Réservation annulée : les dates ont été libérées.');
        }

        return $redirect;
    }
}
