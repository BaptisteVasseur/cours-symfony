<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\ReservationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/host/reservations', name: 'app_host_reservations')]
final class HostReservationController extends AbstractController
{
    /**
     * List pending reservations for the host's properties.
     */
    #[Route('', name: 'app_host_reservations_list', methods: ['GET'])]
    public function list(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Get all pending reservations for host's properties
        $reservations = $reservationRepository->findByHostForListing($user);

        // Group by status
        $pendingReservations = [];
        $confirmedReservations = [];
        $completedReservations = [];
        $cancelledReservations = [];

        foreach ($reservations as $reservation) {
            match ($reservation->getStatus()) {
                'pending' => $pendingReservations[] = $reservation,
                'confirmed' => $confirmedReservations[] = $reservation,
                'completed' => $completedReservations[] = $reservation,
                'cancelled' => $cancelledReservations[] = $reservation,
                default => null,
            };
        }

        return $this->render('front/host/reservations/list.html.twig', [
            'pendingReservations' => $pendingReservations,
            'confirmedReservations' => $confirmedReservations,
            'completedReservations' => $completedReservations,
            'cancelledReservations' => $cancelledReservations,
        ]);
    }

    /**
     * View reservation details and perform actions (confirm/reject/cancel).
     */
    #[Route('/{id}', name: 'app_host_reservations_detail', methods: ['GET', 'POST'])]
    public function detail(
        Reservation $reservation,
        Request $request,
        ReservationRepository $reservationRepository,
        ReservationManager $reservationManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Verify that the user is the host of the property
        if ($reservation->getProperty()->getHost()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette réservation.');
        }

        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;

        if ($request->isMethod('POST')) {
            $action = $request->request->getString('action');
            $reason = $request->request->getString('reason', '');

            try {
                match ($action) {
                    'confirm' => $reservationManager->confirm($reservation, $user),
                    'reject' => $reservationManager->reject($reservation, $user, $reason),
                    'cancel' => $reservationManager->cancel($reservation, $user, $reason),
                    default => throw new \InvalidArgumentException('Action non valide.'),
                };

                $this->addFlash('success', 'La réservation a été mise à jour avec succès.');

                return $this->redirectToRoute('app_host_reservations_list');
            } catch (\LogicException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors du traitement de la réservation.');
            }
        }

        return $this->render('front/host/reservations/detail.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}
