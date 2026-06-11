<?php

namespace App\Controller\Admin;

use App\Repository\BookingRepository;
use App\Repository\ListingRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function index(
        ListingRepository $listingRepository,
        UserRepository $userRepository,
        BookingRepository $bookingRepository,
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'listingCount' => $listingRepository->count([]),
            'userCount' => $userRepository->count([]),
            'bookingCount' => $bookingRepository->count([]),
            'latestBookings' => $bookingRepository->findBy([], ['bookedAt' => 'DESC'], 5),
        ]);
    }
}
