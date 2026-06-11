<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\PropertyRepository;
use App\Repository\ReportRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class HomeController extends AbstractController
{
    #[Route('', name: 'app_admin_home')]
    public function index(
        PropertyRepository $propertyRepository,
        ReservationRepository $reservationRepository,
        ReportRepository $reportRepository,
        UserRepository $userRepository,
    ): Response {
        return $this->render('admin/home/index.html.twig', [
            'totalProperties' => $propertyRepository->countAll(),
            'revenue' => $reservationRepository->sumCompletedRevenue(),
            'openReports' => $reportRepository->countOpen(),
            'activeUsers' => $userRepository->countActive(),
            'pendingProperties' => $propertyRepository->findPendingForModeration(10),
            'recentReservations' => $reservationRepository->findRecentReservations(10),
        ]);
    }



    #[Route('/admin/reservation/{id}/confirm', name: 'app_admin_reservation_confirm', methods: ['POST'])]
    public function confirmReservation(
        Reservation $reservation,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($reservation->getStatus() === 'pending') {
            $reservation->setStatus('confirmed');
            $entityManager->flush();
            $this->addFlash('success', 'Réservation confirmée avec succès.');
        }

        return $this->redirectToRoute('app_admin_home');
    }
}
