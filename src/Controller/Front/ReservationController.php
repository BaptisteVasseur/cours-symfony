<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Form\CancelReservationType;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationService;
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

    #[Route('/{id}/annuler', name: 'app_reservation_cancel', methods: ['GET', 'POST'])]
    #[IsGranted(ReservationVoter::CANCEL, subject: 'reservation')]
    public function cancel(
        Reservation $reservation,
        Request $request,
        ReservationRepository $reservationRepository,
        ReservationService $reservationService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $cancellableStatuses = ['pending', 'confirmed'];
        if (!in_array($reservation->getStatus(), $cancellableStatuses, true)) {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;

        $form = $this->createForm(CancelReservationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{reason: string} $data */
            $data = $form->getData();

            try {
                $reservationService->cancelReservation($reservation, $user, $data['reason']);
                $this->addFlash('success', 'Votre réservation a bien été annulée. Les deux parties ont été notifiées par email.');
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('front/reservation/cancel.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }
}
