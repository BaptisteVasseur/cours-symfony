<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Form\CancelReservationType;
use App\Form\RefuseReservationType;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\Booking\BookingService;
use App\Service\Booking\BookingUnavailableException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/reservations')]
#[IsGranted('ROLE_USER')]
final class HostBookingController extends AbstractController
{
    #[Route('', name: 'app_host_bookings', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/booking/index.html.twig', [
            'pendingReservations' => $reservationRepository->findByHostForListing($user, ['pending']),
            'otherReservations' => $reservationRepository->findByHostForListing($user, ['confirmed', 'completed', 'cancelled']),
        ]);
    }

    #[Route('/{id}', name: 'app_host_booking_moderate', methods: ['GET', 'POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function moderate(
        Request $request,
        Reservation $reservation,
        ReservationRepository $reservationRepository,
        BookingService $bookingService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;
        $refuseForm = $this->createForm(RefuseReservationType::class);
        $refuseForm->handleRequest($request);
        $cancelForm = $this->createForm(CancelReservationType::class, null, [
            'action' => $this->generateUrl('app_host_booking_cancel', ['id' => $reservation->getId()]),
        ]);

        $action = $request->request->get('action');

        if ($request->isMethod('POST') && $action === 'accept') {
            if (!$this->isCsrfTokenValid('reservation_accept_'.$reservation->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');

                return $this->redirectToRoute('app_host_booking_moderate', ['id' => $reservation->getId()]);
            }

            try {
                $bookingService->acceptReservation($reservation, $user);
                $this->addFlash('success', 'La réservation a été acceptée.');
            } catch (BookingUnavailableException $e) {
                $this->addFlash('error', 'Acceptation impossible : '.$e->getMessage());
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_host_booking_moderate', ['id' => $reservation->getId()]);
        }

        if ($refuseForm->isSubmitted() && $refuseForm->isValid()) {
            try {
                $bookingService->refuseReservation($reservation, $user, (string) $refuseForm->get('reason')->getData());
                $this->addFlash('success', 'La demande a été refusée.');

                return $this->redirectToRoute('app_host_booking_moderate', ['id' => $reservation->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('front/host/booking/moderate.html.twig', [
            'reservation' => $reservation,
            'refuseForm' => $refuseForm,
            'cancelForm' => $cancelForm,
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_host_booking_cancel', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function cancel(
        Request $request,
        Reservation $reservation,
        BookingService $bookingService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getStatus() !== 'confirmed') {
            $this->addFlash('error', 'Seules les réservations confirmées peuvent être annulées par l\'hôte.');

            return $this->redirectToRoute('app_host_booking_moderate', ['id' => $reservation->getId()]);
        }

        $form = $this->createForm(CancelReservationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $bookingService->cancelReservation($reservation, $user, (string) $form->get('reason')->getData());
                $this->addFlash('success', 'Réservation annulée. Le voyageur a été notifié.');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_host_booking_moderate', ['id' => $reservation->getId()]);
    }
}
