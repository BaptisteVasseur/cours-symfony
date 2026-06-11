<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ReservationController extends AbstractController
{
    /**
     * Page d'historique : uniquement les réservations du voyageur connecté.
     */
    #[Route('/reservations', name: 'app_reservation_history')]
    #[IsGranted('ROLE_USER')]
    public function history(ReservationRepository $reservationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('pages/reservation/history.html.twig', [
            'reservations' => $reservationRepository->findHistory($user),
        ]);
    }

    /**
     * Page de confirmation de réservation -> entity.
     * Reservation auto-résolue depuis l'uuid {id} (EntityValueResolver).
     */
    #[Route('/reservation/{id}/confirmation', name: 'app_reservation_confirmation')]
    #[IsGranted('ROLE_USER')]
    public function confirmation(Reservation $reservation): Response
    {
        return $this->render('pages/reservation/confirmation.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}
