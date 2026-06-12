<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\Availability\Exception\PropertyNotAvailableException;
use App\Service\Reservation\Exception\InvalidReservationTransitionException;
use App\Service\Reservation\ReservationWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/demandes')]
#[IsGranted('ROLE_USER')]
final class ReservationModerationController extends AbstractController
{
    #[Route('', name: 'app_host_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('host/reservation/index.html.twig', [
            'reservations' => $reservationRepository->findPendingForHost($user),
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(Request $request, Reservation $reservation, ReservationWorkflow $reservationWorkflow): Response
    {
        $user = $this->getUser();
        if (
            !$user instanceof User
            || !$this->isCsrfTokenValid('accept_reservation_' . $reservation->getId(), (string) $request->request->get('_token'))
        ) {
            return $this->redirectToRoute('app_host_reservation_index');
        }

        try {
            $reservationWorkflow->confirm($reservation, $user);
            $this->addFlash('success', 'Réservation acceptée et confirmée.');
        } catch (PropertyNotAvailableException $exception) {
            $this->addFlash('error', 'Acceptation impossible : ' . $exception->getMessage());
        } catch (InvalidReservationTransitionException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_reservation_index');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_refuse', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function refuse(Request $request, Reservation $reservation, ReservationWorkflow $reservationWorkflow): Response
    {
        $user = $this->getUser();
        if (
            !$user instanceof User
            || !$this->isCsrfTokenValid('refuse_reservation_' . $reservation->getId(), (string) $request->request->get('_token'))
        ) {
            return $this->redirectToRoute('app_host_reservation_index');
        }

        $reason = trim((string) $request->request->get('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_reservation_index');
        }

        try {
            $reservationWorkflow->refuse($reservation, $user, $reason);
            $this->addFlash('success', 'Demande refusée.');
        } catch (InvalidReservationTransitionException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_reservation_index');
    }
}
