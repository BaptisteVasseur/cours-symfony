<?php

namespace App\Controller;

use App\Repository\BookingRepository;
use App\Repository\ListingRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(
        UserRepository $userRepository,
        ListingRepository $listingRepository,
        BookingRepository $bookingRepository,
        ReportRepository $reportRepository,
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'usersCount' => $userRepository->count([]),
            'listingsCount' => $listingRepository->count([]),
            'bookingsCount' => $bookingRepository->count([]),
            'reportsCount' => $reportRepository->count([]),
            'recentBookings' => $bookingRepository->findBy([], ['createdAt' => 'DESC'], 5),
            'recentUsers' => $userRepository->findBy([], ['createdAt' => 'DESC'], 5),
        ]);
    }
}
