<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Message\ReservationCancelledMessage;
use App\Message\ReservationConfirmedMessage;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/reservations')]
#[IsGranted('ROLE_USER')]
final class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_reservations', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservations.html.twig', [
            'pending' => $reservationRepository->findPendingByHost($user),
            'all' => $reservationRepository->findAllByHost($user),
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
        MessageBusInterface $bus,
    ): Response {
        if (!$this->isCsrfTokenValid('host_reservation_' . $reservation->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être acceptée.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($reservation->getStatus());
        $history->setNewStatus('confirmed');
        $history->setChangedBy($this->getUser());

        $reservation->setStatus('confirmed');
        $em->persist($history);
        $em->flush();

        $bus->dispatch(new ReservationConfirmedMessage((string) $reservation->getId()));

        $this->addFlash('success', 'Réservation acceptée.');

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_refuse', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function refuse(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
        MessageBusInterface $bus,
    ): Response {
        if (!$this->isCsrfTokenValid('host_reservation_' . $reservation->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être refusée.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $reason = trim($request->request->getString('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif de refus est obligatoire.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($reservation->getStatus());
        $history->setNewStatus('cancelled');
        $history->setChangedBy($this->getUser());

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $em->persist($history);
        $em->flush();

        $bus->dispatch(new ReservationCancelledMessage((string) $reservation->getId()));

        $this->addFlash('success', 'Réservation refusée.');

        return $this->redirectToRoute('app_host_reservations');
    }
}