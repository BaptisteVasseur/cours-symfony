<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Form\RejectReservationType;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/demandes')]
#[IsGranted('ROLE_HOST')]
final class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_requests', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('front/host/reservations/index.html.twig', [
            'reservations' => $reservationRepository->findPendingForHost($user),
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_request_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(
        Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
    ): Response {
        if (!$this->isCsrfTokenValid('accept_reservation_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');

            return $this->redirectToRoute('app_host_requests');
        }

        try {
            /** @var User $user */
            $user = $this->getUser();
            $reservationService->acceptReservation($reservation, $user);
            $this->addFlash('success', 'La réservation a bien été acceptée. Le voyageur en sera notifié par email.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_requests');
    }

    #[Route('/{id}/refuser', name: 'app_host_request_reject', methods: ['GET', 'POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function reject(
        Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
    ): Response {
        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation n\'est plus en attente.');

            return $this->redirectToRoute('app_host_requests');
        }

        $form = $this->createForm(RejectReservationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @var User $user */
                $user = $this->getUser();
                /** @var array{reason: string} $data */
                $data = $form->getData();
                $reservationService->rejectReservation($reservation, $user, $data['reason']);
                $this->addFlash('success', 'La demande a bien été refusée. Le voyageur en sera notifié par email.');
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_host_requests');
        }

        return $this->render('front/host/reservations/reject.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }
}
