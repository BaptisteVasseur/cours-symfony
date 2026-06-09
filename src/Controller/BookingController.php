<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Property;
use App\Form\BookingType;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bookings')]
#[IsGranted('ROLE_USER')]
class BookingController extends AbstractController
{
    #[Route('', name: 'app_booking_index')]
    public function index(BookingRepository $repo): Response
    {
        return $this->render('booking/index.html.twig', [
            'bookings' => $repo->findByTraveler($this->getUser()),
        ]);
    }

    #[Route('/new/{id}', name: 'app_booking_new', methods: ['GET', 'POST'])]
    public function new(Property $property, Request $request, EntityManagerInterface $em, BookingRepository $bookingRepo): Response
    {
        $booking = new Booking();
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $checkIn = $booking->getCheckIn();
            $checkOut = $booking->getCheckOut();

            if ($checkOut <= $checkIn) {
                $this->addFlash('error', 'La date de départ doit être après la date d\'arrivée.');
                return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
            }

            if ($booking->getGuestsCount() > $property->getMaxGuests()) {
                $this->addFlash('error', 'Trop de voyageurs pour ce logement.');
                return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
            }

            if ($bookingRepo->hasConflict($checkIn, $checkOut, (string) $property->getId())) {
                $this->addFlash('error', 'Ces dates ne sont pas disponibles.');
                return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
            }

            $nights = $checkIn->diff($checkOut)->days;
            $totalPrice = (string) ($nights * (float) $property->getPricePerNight());

            $booking->setProperty($property);
            $booking->setTraveler($this->getUser());
            $booking->setTotalPrice($totalPrice);

            $em->persist($booking);
            $em->flush();

            return $this->redirectToRoute('app_booking_confirmation', ['id' => $booking->getId()]);
        }

        return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
    }

    #[Route('/{id}/confirmation', name: 'app_booking_confirmation')]
    public function confirmation(Booking $booking): Response
    {
        if ($booking->getTraveler() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('booking/confirmation.html.twig', ['booking' => $booking]);
    }

    #[Route('/{id}', name: 'app_booking_show')]
    public function show(Booking $booking): Response
    {
        if ($booking->getTraveler() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('booking/show.html.twig', ['booking' => $booking]);
    }
}
