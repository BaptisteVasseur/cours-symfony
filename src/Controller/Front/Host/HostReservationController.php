<?php

declare(strict_types=1);

namespace App\Controller\Front\Host;

use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\BookingCancelledMessage;
use App\Message\BookingConfirmedMessage;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/reservations')]
#[IsGranted('ROLE_HOST')]
final class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_reservation_index', methods: ['GET'])]
    public function index(
        ReservationRepository $reservationRepository,
        PropertyRepository $propertyRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservation/index.html.twig', [
            'reservations' => $reservationRepository->findByHostForDashboard($user),
            'properties'   => $propertyRepository->findByHost($user),
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    public function accept(
        string $id,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('reservation_accept_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_reservation_index');
        }

        $reservation = $reservationRepository->find($id);
        if ($reservation === null || (string) $reservation->getProperty()?->getHost()?->getId() !== (string) $user->getId()) {
            throw $this->createNotFoundException();
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut pas être acceptée.');

            return $this->redirectToRoute('app_host_reservation_index');
        }

        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('confirmed');

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus('confirmed');
        $history->setChangedBy($user);
        $reservation->addStatusHistory($history);

        $entityManager->persist($history);
        $entityManager->flush();

        $bus->dispatch(new BookingConfirmedMessage((string) $reservation->getId()));

        $this->addFlash('success', 'Réservation acceptée. Le voyageur a été notifié.');

        return $this->redirectToRoute('app_host_reservation_index');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_refuse', methods: ['POST'])]
    public function refuse(
        string $id,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('reservation_refuse_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_reservation_index');
        }

        $reservation = $reservationRepository->find($id);
        if ($reservation === null || (string) $reservation->getProperty()?->getHost()?->getId() !== (string) $user->getId()) {
            throw $this->createNotFoundException();
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut pas être refusée.');

            return $this->redirectToRoute('app_host_reservation_index');
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_reservation_index');
        }

        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus('cancelled');
        $history->setChangedBy($user);
        $reservation->addStatusHistory($history);

        $entityManager->persist($history);
        $entityManager->flush();

        $bus->dispatch(new BookingCancelledMessage((string) $reservation->getId(), $reason, 'hôte'));

        $this->addFlash('success', 'Réservation refusée. Le voyageur a été notifié.');

        return $this->redirectToRoute('app_host_reservation_index');
    }

    #[Route('/{id}/annuler', name: 'app_host_reservation_cancel', methods: ['POST'])]
    public function cancel(
        string $id,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('reservation_cancel_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_reservation_index');
        }

        $reservation = $reservationRepository->find($id);
        if ($reservation === null || (string) $reservation->getProperty()?->getHost()?->getId() !== (string) $user->getId()) {
            throw $this->createNotFoundException();
        }

        if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            $this->addFlash('error', 'Cette réservation ne peut pas être annulée.');

            return $this->redirectToRoute('app_host_reservation_index');
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif d\'annulation est obligatoire.');

            return $this->redirectToRoute('app_host_reservation_index');
        }

        $oldStatus = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus('cancelled');
        $history->setChangedBy($user);
        $reservation->addStatusHistory($history);

        $entityManager->persist($history);
        $entityManager->flush();

        $bus->dispatch(new BookingCancelledMessage((string) $reservation->getId(), $reason, 'hôte'));

        $this->addFlash('success', 'Réservation annulée. Le voyageur a été notifié.');

        return $this->redirectToRoute('app_host_reservation_index');
    }
}
