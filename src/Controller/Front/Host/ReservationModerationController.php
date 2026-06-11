<?php

declare(strict_types=1);

namespace App\Controller\Front\Host;

use App\Entity\Reservation;
use App\Entity\User;
use App\Controller\Front\BookingController;
use App\Message\BookingCancelledMessage;
use App\Message\BookingConfirmedMessage;
use App\Service\NotificationService;
use App\Repository\ReservationRepository;
use App\Service\ReservationWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/reservations')]
#[IsGranted('ROLE_USER')]
final class ReservationModerationController extends AbstractController
{
    #[Route('', name: 'app_host_reservations', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservations/index.html.twig', [
            'reservations' => $reservationRepository->findPendingByHost($user),
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    public function accept(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
        ReservationWorkflowService $workflowService,
        MessageBusInterface $bus,
        NotificationService $notificationService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('moderation_'.$reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_host_reservations');
        }

        if ($reservation->getProperty()?->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être acceptée.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $workflowService->transition($reservation, 'confirmed', $user);
        $notificationService->notifyBookingConfirmed($reservation);
        $em->flush();

        $bus->dispatch(new BookingConfirmedMessage((string) $reservation->getId()));
        BookingController::dispatchCheckinReminder($bus, $reservation);
        $this->addFlash('success', 'Réservation acceptée.');

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_reject', methods: ['POST'])]
    public function reject(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
        ReservationWorkflowService $workflowService,
        MessageBusInterface $bus,
        NotificationService $notificationService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('moderation_'.$reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_host_reservations');
        }

        if ($reservation->getProperty()?->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être refusée.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif de refus est obligatoire.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $workflowService->transition($reservation, 'cancelled', $user, $reason);
        $notificationService->notifyBookingCancelled($reservation);
        $em->flush();

        $bus->dispatch(new BookingCancelledMessage((string) $reservation->getId()));
        $this->addFlash('success', 'Demande refusée.');

        return $this->redirectToRoute('app_host_reservations');
    }
}
