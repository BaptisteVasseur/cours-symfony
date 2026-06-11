<?php

namespace App\Controller;

use App\Repository\BookingRepository;
use App\Repository\ListingRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
final class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(
        ListingRepository $listingRepository,
        UserRepository $userRepository,
        BookingRepository $bookingRepository,
        ReviewRepository $reviewRepository,
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'totalListings'  => count($listingRepository->findAll()),
            'activeListings' => count($listingRepository->findBy(['isActive' => true])),
            'totalUsers'     => count($userRepository->findAll()),
            'totalBookings'  => count($bookingRepository->findAll()),
            'totalReviews'   => count($reviewRepository->findAll()),
            'recentListings' => $listingRepository->findBy([], ['createdAt' => 'DESC'], 5),
        ]);
    }
}
