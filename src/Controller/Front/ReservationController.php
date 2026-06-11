<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\BookingService;
use App\Service\Exception\InvalidReservationTransitionException;
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
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function cancel(Request $request, Reservation $reservation, BookingService $bookingService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('cancel_reservation_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $reason = trim((string) $request->request->get('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif d\'annulation est obligatoire.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        try {
            $bookingService->cancel($reservation, $user, $reason);
            $this->addFlash('success', 'Votre réservation a été annulée.');
        } catch (InvalidReservationTransitionException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
    }
}
