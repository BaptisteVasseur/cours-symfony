<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Form\CancellationReasonType;
use App\Message\ReservationCancelledMessage;
use App\Service\ReservationStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\ReservationVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CancellationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly ReservationStatusService $statusService,
    ) {}

    #[Route('/reservations/{id}/annuler', name: 'app_reservation_cancel', methods: ['GET', 'POST'])]
    public function cancel(?Reservation $reservation, Request $request): Response
    {
        if (!$reservation) {
            $this->addFlash('error', 'Cette réservation n\'existe plus.');
            return $this->redirectToRoute('app_reservation_index');
        }

        // Access check done here (not via attribute) because $reservation can be null
        $this->denyAccessUnlessGranted(ReservationVoter::VIEW, $reservation);

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $allowedStatuses = ['pending', 'confirmed'];
        if (!in_array($reservation->getStatus(), $allowedStatuses, true)) {
            $this->addFlash('error', 'Cette réservation ne peut pas être annulée (statut : ' . $reservation->getStatus() . ').');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $form = $this->createForm(CancellationReasonType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reason = $form->get('reason')->getData();

            $oldStatus = $reservation->getStatus();
            $reservation->setCancellationReason($reason);
            $this->statusService->transition($reservation, 'cancelled', $user, $oldStatus);
            $this->em->flush();

            // Dispatch after flush
            $this->bus->dispatch(new ReservationCancelledMessage((string) $reservation->getId(), $reason));

            $this->addFlash('success', 'Votre réservation a été annulée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('front/host/reservations/cancel.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

}
