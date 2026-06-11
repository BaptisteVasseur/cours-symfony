<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Form\CancelReservationType;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\Booking\BookingService;
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
    public function show(
        Reservation $reservation,
        ReservationRepository $reservationRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;
        $isGuest = $reservation->getGuest() !== null
            && (string) $reservation->getGuest()->getId() === (string) $user->getId();
        $cancellable = $isGuest && in_array($reservation->getStatus(), ['pending', 'confirmed'], true);
        $cancelForm = $cancellable
            ? $this->createForm(CancelReservationType::class, null, [
                'action' => $this->generateUrl('app_reservation_cancel', ['id' => $reservation->getId()]),
            ])->createView()
            : null;

        return $this->render('front/reservation/show.html.twig', [
            'reservation' => $reservation,
            'cancelForm' => $cancelForm,
            'cancellable' => $cancellable,
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_reservation_cancel', methods: ['POST'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function cancel(
        Request $request,
        Reservation $reservation,
        BookingService $bookingService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(CancelReservationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $bookingService->cancelReservation($reservation, $user, (string) $form->get('reason')->getData());
                $this->addFlash('success', 'Réservation annulée. Les dates ont été libérées.');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
    }
}
