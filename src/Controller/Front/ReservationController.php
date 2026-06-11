<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationService;
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

    #[Route('/book/{id}', name: 'app_reservation_book', methods: ['GET', 'POST'])]
    public function book(Property $property, Request $request, ReservationService $reservationService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $checkinStr = $request->get('checkin', '');
        $checkoutStr = $request->get('checkout', '');
        $guests = (int) $request->get('guests', 1);

        $checkin = \DateTimeImmutable::createFromFormat('Y-m-d', $checkinStr);
        $checkout = \DateTimeImmutable::createFromFormat('Y-m-d', $checkoutStr);

        if ($request->isMethod('POST')) {
            if (!$checkin || !$checkout || $checkout <= $checkin) {
                $this->addFlash('error', 'Les dates sélectionnées sont invalides.');
                return $this->redirectToRoute('app_reservation_book', ['id' => $property->getId()]);
            }

            try {
                $reservation = $reservationService->create($property, $user, $checkin, $checkout, $guests);
                $this->addFlash('success', $property->isInstantBooking()
                    ? 'Réservation confirmée !'
                    : 'Demande envoyée, en attente de validation par l\'hôte.'
                );
                return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('front/reservation/booking.html.twig', [
            'property' => $property,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'guests' => $guests,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_reservation_cancel', methods: ['POST'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function cancel(Reservation $reservation, Request $request, ReservationService $reservationService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reason = $request->request->get('reason', '');

        if (empty($reason)) {
            $this->addFlash('error', 'Un motif d\'annulation est obligatoire.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        try {
            $reservationService->cancel($reservation, $user, $reason);
            $this->addFlash('success', 'Réservation annulée.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_reservation_index');
    }
}