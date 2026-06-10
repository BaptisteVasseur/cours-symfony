<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host')]
#[IsGranted('ROLE_HOST')]
class HostDashboardController extends AbstractController
{
    #[Route('', name: 'app_host_dashboard', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        $properties = $user->getProperties();

        $allReservations = $reservationRepository->findByHost($user);
        $recentReservations = array_slice($allReservations, 0, 5);

        $revenue = $reservationRepository->sumCompletedRevenueByHost($user);

        return $this->render('host/dashboard.html.twig', [
            'totalProperties' => count($properties),
            'revenue' => $revenue,
            'reservations' => $recentReservations,
        ]);
    }
}
