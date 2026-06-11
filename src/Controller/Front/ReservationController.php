<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Exception\ReservationWorkflowException;
use App\Form\ReservationCancelType;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationWorkflowService;
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
            'canCancel' => \in_array($reservation->getStatus(), ['pending', 'confirmed'], true),
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_reservation_cancel', methods: ['GET', 'POST'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function cancel(
        Request $request,
        Reservation $reservation,
        ReservationWorkflowService $workflowService,
    ): Response {
        if (!\in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $form = $this->createForm(ReservationCancelType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user = $this->getUser();
                if (!$user instanceof User) {
                    return $this->redirectToRoute('app_login');
                }

                $workflowService->cancel($reservation, $user, (string) $form->get('reason')->getData());
                $this->addFlash('success', 'La réservation a été annulée.');

                return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
            } catch (ReservationWorkflowException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('front/reservation/cancel.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }
}
