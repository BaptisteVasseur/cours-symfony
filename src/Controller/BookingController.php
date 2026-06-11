<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Form\BookingType;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class BookingController extends AbstractController
{

    #[Route('/my-bookings', name: 'app_my_bookings', methods: ['GET'])]
    public function myBookings(BookingRepository $bookingRepository): Response
    {
        $bookings = $bookingRepository->findBy(
            ['guest' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('booking/my_bookings.html.twig', [
            'bookings' => $bookings,
        ]);
    }

    #[Route('/booking', name: 'app_booking_index', methods: ['GET'])]
    public function index(BookingRepository $bookingRepository): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $bookings = $bookingRepository->findBy([], ['createdAt' => 'DESC']);
        } else {
            $bookings = $bookingRepository->findBy(['guest' => $this->getUser()], ['createdAt' => 'DESC']);
        }

        return $this->render('booking/index.html.twig', [
            'bookings' => $bookings,
        ]);
    }

    #[Route('/booking/new', name: 'app_booking_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $booking = new Booking();
        $booking->setGuest($this->getUser());
        $booking->setBookingStatus('pending');
        $booking->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($booking);
            $em->flush();

            $this->addFlash('success', 'Réservation enregistrée.');

            return $this->redirectToRoute('app_my_bookings');
        }

        return $this->render('booking/new.html.twig', [
            'booking' => $booking,
            'form' => $form,
        ]);
    }

    #[Route('/booking/{id}', name: 'app_booking_show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function show(Booking $booking): Response
    {
        $this->denyAccessUnlessOwnerOrAdmin($booking);

        return $this->render('booking/show.html.twig', [
            'booking' => $booking,
        ]);
    }

    #[Route('/booking/{id}/edit', name: 'app_booking_edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function edit(Request $request, Booking $booking, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessOwnerOrAdmin($booking);

        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Réservation mise à jour.');

            return $this->redirectToRoute('app_booking_index');
        }

        return $this->render('booking/edit.html.twig', [
            'booking' => $booking,
            'form' => $form,
        ]);
    }

    #[Route('/booking/{id}', name: 'app_booking_delete', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function delete(Request $request, Booking $booking, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessOwnerOrAdmin($booking);

        if ($this->isCsrfTokenValid('delete' . $booking->getId(), $request->request->get('_token'))) {
            $em->remove($booking);
            $em->flush();
            $this->addFlash('success', 'Réservation supprimée.');
        }

        return $this->redirectToRoute('app_booking_index');
    }

    private function denyAccessUnlessOwnerOrAdmin(Booking $booking): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && $booking->getGuest() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez accéder qu\'à vos propres réservations.');
        }
    }
}
