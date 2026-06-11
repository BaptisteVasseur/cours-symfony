<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\BookingException;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host/reservations')]
#[IsGranted('ROLE_HOST')]
final class ReservationModerationController extends AbstractController
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    /**
     * Tableau de bord des demandes en attente (Pending) adressées à l'hôte.
     */
    #[Route('', name: 'app_host_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('host/reservation/index.html.twig', [
            'reservations' => $reservationRepository->findPendingForHost($user),
        ]);
    }

    #[Route('/{id}/accept', name: 'app_host_reservation_accept', methods: ['POST'])]
    public function accept(Reservation $reservation, Request $request): Response
    {
        $this->denyUnlessOwner($reservation);
        $this->guardCsrf($reservation, $request);

        try {
            $this->bookingService->confirm($reservation, $this->currentUser());
            $this->addFlash('success', 'Demande acceptée.');
        } catch (BookingException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservation_index');
    }

    #[Route('/{id}/refuse', name: 'app_host_reservation_refuse', methods: ['POST'])]
    public function refuse(Reservation $reservation, Request $request): Response
    {
        $this->denyUnlessOwner($reservation);
        $this->guardCsrf($reservation, $request);

        try {
            $this->bookingService->refuse($reservation, $this->currentUser(), (string) $request->request->get('reason'));
            $this->addFlash('success', 'Demande refusée.');
        } catch (BookingException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservation_index');
    }

    private function guardCsrf(Reservation $reservation, Request $request): void
    {
        if (!$this->isCsrfTokenValid('moderate' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
    }

    private function denyUnlessOwner(Reservation $reservation): void
    {
        if ($reservation->getProperty()?->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Cette réservation ne concerne pas vos logements.');
        }
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
