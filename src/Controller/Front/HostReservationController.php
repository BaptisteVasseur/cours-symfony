<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Exception\ReservationWorkflowException;
use App\Form\ReservationRejectType;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationWorkflowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/reservations/demandes')]
#[IsGranted('ROLE_USER')]
final class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/account/host_reservations.html.twig', [
            'reservations' => $reservationRepository->findPendingByHost($user),
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(
        Request $request,
        Reservation $reservation,
        ReservationWorkflowService $workflowService,
    ): Response {
        if (!$this->isCsrfTokenValid('accept' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                return $this->redirectToRoute('app_login');
            }

            $workflowService->confirm($reservation, $user);
            $this->addFlash('success', 'La réservation a été acceptée.');
        } catch (ReservationWorkflowException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservation_index');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_reject', methods: ['GET', 'POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function reject(
        Request $request,
        Reservation $reservation,
        ReservationWorkflowService $workflowService,
    ): Response {
        $form = $this->createForm(ReservationRejectType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user = $this->getUser();
                if (!$user instanceof User) {
                    return $this->redirectToRoute('app_login');
                }

                $workflowService->reject($reservation, $user, (string) $form->get('reason')->getData());
                $this->addFlash('success', 'La demande a été refusée.');

                return $this->redirectToRoute('app_host_reservation_index');
            } catch (ReservationWorkflowException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('front/account/host_reservation_reject.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }
}
