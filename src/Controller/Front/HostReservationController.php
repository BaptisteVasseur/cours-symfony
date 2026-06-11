<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Form\CancellationReasonType;
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
    #[Route('', name: 'app_host_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $pending = $reservationRepository->findPendingForHost($user);
        $others  = $reservationRepository->findNonPendingForHost($user);

        return $this->render('front/host_reservation/index.html.twig', [
            'pendingReservations' => $pending,
            'otherReservations'   => $others,
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
    ): Response {
        if (!$this->isCsrfTokenValid('accept' . $reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être acceptée.');
            return $this->redirectToRoute('app_host_reservation_index');
        }

        $reservation->setStatus('confirmed');

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('confirmed');
        $history->setChangedBy($this->getUser());

        $entityManager->persist($history);
        $entityManager->flush();

        $bus->dispatch(new ReservationConfirmedMessage((string) $reservation->getId()));

        $this->addFlash('success', 'Réservation acceptée. Le voyageur a été notifié.');

        return $this->redirectToRoute('app_host_reservation_index');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_reject', methods: ['GET', 'POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function reject(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
    ): Response {
        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être refusée.');
            return $this->redirectToRoute('app_host_reservation_index');
        }

        $form = $this->createForm(CancellationReasonType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reason = $form->getData()['reason'];

            $reservation->setStatus('rejected');
            $reservation->setCancellationReason($reason);

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus('pending');
            $history->setNewStatus('rejected');
            $history->setChangedBy($this->getUser());

            $entityManager->persist($history);
            $entityManager->flush();

            $bus->dispatch(new ReservationCancelledMessage(
                (string) $reservation->getId(),
                'l\'hôte',
                $reason,
            ));

            $this->addFlash('success', 'Réservation refusée. Le voyageur a été notifié.');

            return $this->redirectToRoute('app_host_reservation_index');
        }

        return $this->render('front/host_reservation/reject.html.twig', [
            'reservation' => $reservation,
            'form'        => $form,
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_host_reservation_cancel', methods: ['GET', 'POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function cancel(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
    ): Response {
        if (!in_array($reservation->getStatus(), ['confirmed', 'pending'], true)) {
            $this->addFlash('error', 'Cette réservation ne peut pas être annulée.');
            return $this->redirectToRoute('app_host_reservation_index');
        }

        $form = $this->createForm(CancellationReasonType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reason      = $form->getData()['reason'];
            $oldStatus   = $reservation->getStatus();

            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason($reason);

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus($oldStatus);
            $history->setNewStatus('cancelled');
            $history->setChangedBy($this->getUser());

            $entityManager->persist($history);
            $entityManager->flush();

            $bus->dispatch(new ReservationCancelledMessage(
                (string) $reservation->getId(),
                'l\'hôte',
                $reason,
            ));

            $this->addFlash('success', 'Réservation annulée.');

            return $this->redirectToRoute('app_host_reservation_index');
        }

        return $this->render('front/host_reservation/cancel.html.twig', [
            'reservation' => $reservation,
            'form'        => $form,
        ]);
    }
}
