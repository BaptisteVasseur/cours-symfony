<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Form\CancellationReasonType;
use App\Message\ReservationStatusChangedMessage;
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
    #[Route('', name: 'app_host_reservations_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservations/index.html.twig', [
            'reservations' => $reservationRepository->findPendingForHost($user),
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_reservations_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
    ): Response {
        if (!$this->isCsrfTokenValid('accept_reservation_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');

            return $this->redirectToRoute('app_host_reservations_index');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être acceptée.');

            return $this->redirectToRoute('app_host_reservations_index');
        }

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('confirmed');
        $history->setChangedBy($this->getUser());
        $entityManager->persist($history);

        $reservation->setStatus('confirmed');
        $entityManager->flush();

        $bus->dispatch(new ReservationStatusChangedMessage((string) $reservation->getId(), 'confirmed'));

        $this->addFlash('success', 'Réservation acceptée avec succès.');

        return $this->redirectToRoute('app_host_reservations_index');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservations_refuse', methods: ['GET', 'POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function refuse(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
    ): Response {
        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être refusée.');

            return $this->redirectToRoute('app_host_reservations_index');
        }

        $form = $this->createForm(CancellationReasonType::class, null, [
            'label_text' => 'Motif du refus',
            'csrf_token_id' => 'refuse_reservation_' . $reservation->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus('pending');
            $history->setNewStatus('cancelled');
            $history->setChangedBy($this->getUser());
            $entityManager->persist($history);

            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason($form->get('reason')->getData());
            $entityManager->flush();

            $bus->dispatch(new ReservationStatusChangedMessage((string) $reservation->getId(), 'cancelled'));

            $this->addFlash('success', 'Réservation refusée.');

            return $this->redirectToRoute('app_host_reservations_index');
        }

        return $this->render('front/host/reservations/refuse.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }
}
