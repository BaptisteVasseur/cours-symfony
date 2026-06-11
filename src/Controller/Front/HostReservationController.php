<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Form\CancellationReasonType;
use App\Message\ReservationCancelledMessage;
use App\Message\ReservationConfirmedMessage;
use App\Repository\ReservationRepository;
use App\Service\AvailabilityService;
use App\Service\ReservationStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\ReservationVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte')]
#[IsGranted('ROLE_USER')]
final class HostReservationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly AvailabilityService $availabilityService,
        private readonly ReservationStatusService $statusService,
    ) {}

    #[Route('/reservations', name: 'app_host_reservations', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservations/index.html.twig', [
            'reservations' => $reservationRepository->findByHost($user),
        ]);
    }

    #[Route('/reservations/en-attente', name: 'app_host_reservations_pending', methods: ['GET'])]
    public function pending(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservations/pending.html.twig', [
            'reservations' => $reservationRepository->findPendingByHost($user),
        ]);
    }

    #[Route('/reservations/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    public function accept(?Reservation $reservation, Request $request): Response
    {
        if (!$reservation) {
            $this->addFlash('error', 'Cette réservation n\'existe plus.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $this->denyAccessUnlessGranted(ReservationVoter::MANAGE, $reservation);

        if (!$this->isCsrfTokenValid('accept' . $reservation->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_host_reservations_pending');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation n\'est plus en attente.');

            return $this->redirectToRoute('app_host_reservations_pending');
        }

        // Pessimistic lock pattern: re-check availability before confirming
        $property = $reservation->getProperty();
        if (!$this->availabilityService->isAvailable(
            $property,
            $reservation->getCheckinDate(),
            $reservation->getCheckoutDate(),
            $reservation->getGuestsCount(),
        )) {
            $this->addFlash('error', 'Ces dates ne sont plus disponibles. La réservation ne peut pas être confirmée.');

            return $this->redirectToRoute('app_host_reservations_pending');
        }

        $oldStatus = $reservation->getStatus();
        $this->statusService->transition($reservation, 'confirmed', $user, $oldStatus);
        $this->em->flush();

        // Dispatch after flush — reservation is now confirmed in DB
        $this->bus->dispatch(new ReservationConfirmedMessage((string) $reservation->getId()));

        $this->addFlash('success', 'La réservation a été confirmée.');

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/reservations/{id}/refuser', name: 'app_host_reservation_reject', methods: ['GET', 'POST'])]
    public function reject(?Reservation $reservation, Request $request): Response
    {
        if (!$reservation) {
            $this->addFlash('error', 'Cette réservation n\'existe plus.');
            return $this->redirectToRoute('app_host_reservations');
        }

        $this->denyAccessUnlessGranted(ReservationVoter::MANAGE, $reservation);

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation n\'est plus en attente.');

            return $this->redirectToRoute('app_host_reservations_pending');
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

            $this->addFlash('success', 'La réservation a été refusée.');

            return $this->redirectToRoute('app_host_reservations');
        }

        return $this->render('front/host/reservations/reject.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

}
