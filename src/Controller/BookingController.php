<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Security\Voter\BookingVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_USER')]
final class BookingController extends AbstractController
{

    #[Route('/my-bookings', name: 'app_my_bookings', methods: ['GET'])]
    public function myBookings(BookingRepository $bookingRepository): Response
    {
        return $this->render('booking/my_bookings.html.twig', [
            'bookings' => $bookingRepository->findBy(['guest' => $this->getUser()], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/booking', name: 'app_booking_index', methods: ['GET'])]
    public function index(BookingRepository $bookingRepository): Response
    {
        $bookings = $this->isGranted('ROLE_ADMIN')
            ? $bookingRepository->findBy([], ['createdAt' => 'DESC'])
            : $bookingRepository->findBy(['guest' => $this->getUser()], ['createdAt' => 'DESC']);

        return $this->render('booking/index.html.twig', ['bookings' => $bookings]);
    }

    #[Route('/booking/{id}', name: 'app_booking_show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function show(Booking $booking): Response
    {
        $this->denyAccessUnlessGranted(BookingVoter::VIEW, $booking);

        return $this->render('booking/show.html.twig', ['booking' => $booking]);
    }
}
