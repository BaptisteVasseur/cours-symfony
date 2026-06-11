<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ReservationCancellationRequest;
use App\Entity\Reservation;
use App\Entity\Property;
use App\Entity\User;
use App\Form\BookingRequestType;
use App\Form\ReservationCancellationType;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservations')]
final class ReservationController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('home/reservations.html.twig', [
            'reservations' => $reservationRepository->findForGuest($user),
            'pendingHostReservations' => $this->isGranted('ROLE_HOST') ? $reservationRepository->findPendingForHost($user) : [],
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/logement/{id}', name: 'app_reservation_create', methods: ['POST'])]
    public function create(Request $request, Property $property, ReservationService $reservationService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(BookingRequestType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Veuillez corriger les informations de réservation.');

            return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
        }

        $bookingRequest = $form->getData();

        try {
            $reservation = $reservationService->create(
                $property,
                $user,
                $bookingRequest->checkinDate,
                $bookingRequest->checkoutDate,
                (int) $bookingRequest->guestsCount,
            );
            $this->addFlash('success', $reservation->getStatus() === 'confirmed' ? 'Réservation confirmée.' : 'Demande envoyée à l\'hôte.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
        }
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        $this->denyAccessUnlessAllowedToReservation($reservation);
        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;
        $cancellationForm = $this->createForm(ReservationCancellationType::class, new ReservationCancellationRequest(), [
            'action' => $this->generateUrl('app_reservation_cancel', ['id' => $reservation->getId()]),
        ]);

        return $this->render('home/reservation.html.twig', [
            'reservation' => $reservation,
            'cancellationForm' => $cancellationForm,
        ]);
    }

    #[IsGranted('ROLE_HOST')]
    #[Route('/{id}/accept', name: 'app_reservation_accept', methods: ['POST'])]
    public function accept(Request $request, Reservation $reservation, ReservationService $reservationService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isHostForReservation($reservation, $user)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('accept'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        try {
            $reservationService->confirm($reservation, $user);
            $this->addFlash('success', 'Demande acceptée.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/cancel', name: 'app_reservation_cancel', methods: ['POST'])]
    public function cancel(Request $request, Reservation $reservation, ReservationService $reservationService): Response
    {
        $this->denyAccessUnlessAllowedToReservation($reservation);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ReservationCancellationType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Le motif d\'annulation est obligatoire.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        try {
            $reservationService->cancel($reservation, $user, (string) $form->getData()->reason);
            $this->addFlash('success', 'Réservation annulée.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
    }

    private function denyAccessUnlessAllowedToReservation(Reservation $reservation): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getGuest() === $user || $this->isHostForReservation($reservation, $user) || $this->isGranted('ROLE_ADMIN')) {
            return;
        }

        throw $this->createAccessDeniedException();
    }

    private function isHostForReservation(Reservation $reservation, User $user): bool
    {
        return $reservation->getProperty()?->getHost() === $user;
    }
}
