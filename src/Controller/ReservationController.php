<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Reservation;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservations', name: 'app_reservation_')]
class ReservationController extends AbstractController
{
    #[Route('/history', name: 'history')]
    #[isGranted('ROLE_USER')]
    public function history(
        Request $request,
        ReservationRepository $reservationRepository,
        UserRepository $userRepository
    ): Response {
        $status = $request->query->get('status');

        // Utilise guest1 en dur pour tester sans login
        $user = $this->getUser();

        $criteria = ['guest' => $user];
        if ($status) {
            $criteria['status'] = $status;
        }

        $reservations = $reservationRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC']
        );

        return $this->render('reservation/history.html.twig', [
            'reservations'  => $reservations,
            'currentStatus' => $status ?? 'all',
        ]);
    }


   #[Route('/{id}/confirm', name: 'confirm')]
    public function confirm(Reservation $reservation): Response
    {
        return $this->render('reservation/confirm.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}