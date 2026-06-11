<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Security\Roles;
use App\Security\Voter\ReservationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte')]
final class HostDashboardController extends AbstractController
{
    #[Route('/devenir-hote', name: 'app_become_host', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function becomeHost(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (in_array(Roles::HOST, $user->getRoles(), true)) {
            return $this->redirectToRoute('app_host_dashboard');
        }

        if ($request->isMethod('POST')) {
            $user->addAssignedRole(Roles::HOST);
            $entityManager->flush();

            $this->addFlash('success', 'Félicitations ! Vous êtes maintenant un hôte. Bienvenue sur votre tableau de bord !');

            return $this->redirectToRoute('app_host_dashboard');
        }

        return $this->render('front/host_dashboard/devenir_hote.html.twig');
    }

    #[Route('/hote/dashboard', name: 'app_host_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_HOST')]
    public function dashboard(
        ReservationRepository $reservationRepository,
        PropertyRepository $propertyRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $totalEarnings = $reservationRepository->sumConfirmedRevenueByHost($user);
        $pendingCount = $reservationRepository->countPendingReservationsByHost($user);
        $activeListingsCount = $propertyRepository->count(['host' => $user, 'status' => 'published']);
        $totalListings = $propertyRepository->count(['host' => $user]);

        // Calculate a dynamic occupancy rate
        $occupancyRate = 0.0;
        if ($totalListings > 0) {
            $reservations = $reservationRepository->findByHostForListing($user);
            $occupiedDays = 0;
            foreach ($reservations as $res) {
                if (in_array($res->getStatus(), ['confirmed', 'completed'], true)) {
                    $interval = $res->getCheckinDate()->diff($res->getCheckoutDate());
                    $occupiedDays += max(1, $interval->days);
                }
            }
            // Occupancy rate based on occupied days in a simulated 30-day window per listing
            $occupancyRate = round(($occupiedDays / ($totalListings * 30)) * 100, 1);
            if ($occupancyRate > 100.0) {
                $occupancyRate = 100.0;
            }
        }

        $recentReservations = array_slice($reservationRepository->findByHostForListing($user), 0, 5);

        return $this->render('front/host_dashboard/dashboard.html.twig', [
            'totalEarnings' => $totalEarnings,
            'pendingCount' => $pendingCount,
            'activeListingsCount' => $activeListingsCount,
            'occupancyRate' => $occupancyRate,
            'recentReservations' => $recentReservations,
        ]);
    }

    #[Route('/hote/reservations', name: 'app_host_reservations', methods: ['GET'])]
    #[IsGranted('ROLE_HOST')]
    public function reservations(
        Request $request,
        ReservationRepository $reservationRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $status = $request->query->get('status');
        if ($status === 'all' || !in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            $status = null;
        }

        $reservations = $reservationRepository->findByHostForListing($user, $status);

        return $this->render('front/host_dashboard/reservations.html.twig', [
            'reservations' => $reservations,
            'currentStatus' => $status ?? 'all',
        ]);
    }

    #[Route('/hote/reservations/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('accept'.$reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_host_reservations');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être acceptée.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('confirmed');

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus('confirmed');
        $history->setChangedBy($this->getUser());

        $entityManager->persist($history);
        $entityManager->flush();

        $this->addFlash('success', 'La réservation a été acceptée avec succès.');

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/hote/reservations/{id}/refuser', name: 'app_host_reservation_decline', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function decline(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('decline'.$reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_host_reservations');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être refusée.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $reason = trim((string) $request->request->get('cancellation_reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif de refus est obligatoire.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus('cancelled');
        $history->setChangedBy($this->getUser());

        $entityManager->persist($history);
        $entityManager->flush();

        $this->addFlash('success', 'La demande de réservation a été refusée.');

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/hote/reservations/{id}/annuler', name: 'app_host_reservation_cancel', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function cancel(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('cancel'.$reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_host_reservations');
        }

        if ($reservation->getStatus() !== 'confirmed') {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $reason = trim((string) $request->request->get('cancellation_reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif d\'annulation est obligatoire.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus('cancelled');
        $history->setChangedBy($this->getUser());

        $entityManager->persist($history);
        $entityManager->flush();

        $this->addFlash('success', 'La réservation a été annulée avec succès.');

        return $this->redirectToRoute('app_host_reservations');
    }


    #[Route('/reservations/{id}/contact', name: 'app_reservation_contact', methods: ['GET', 'POST'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function contactGuest(
        Reservation $reservation,
        ConversationRepository $conversationRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $conversation = $conversationRepository->findOneBy(['reservation' => $reservation]);

        if ($conversation === null) {
            $conversation = new Conversation();
            $conversation->setReservation($reservation);
            $entityManager->persist($conversation);

            $partGuest = new ConversationParticipant();
            $partGuest->setConversation($conversation);
            $partGuest->setUser($reservation->getGuest());
            $entityManager->persist($partGuest);

            $partHost = new ConversationParticipant();
            $partHost->setConversation($conversation);
            $partHost->setUser($this->getUser());
            $entityManager->persist($partHost);

            $entityManager->flush();
        }

        return $this->redirectToRoute('app_messages_show', ['id' => $conversation->getId()]);
    }
}
