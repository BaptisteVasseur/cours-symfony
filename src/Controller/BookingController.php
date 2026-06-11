<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Property;
use App\Form\BookingType;
use App\Form\CancellationReasonType;
use App\Repository\BookingRepository;
use App\Service\BookingService;
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

    /**
     * Step 1 — validate form data from the property page, then show checkout recap.
     */
    #[Route('/new/{id}', name: 'app_booking_new', methods: ['POST'])]
    public function new(Property $property, Request $request): Response
    {
        $booking = new \App\Entity\Booking();
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Veuillez corriger les erreurs du formulaire.');
            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        return $this->redirectToRoute('app_booking_checkout', [
            'id'       => $property->getId(),
            'checkIn'  => $booking->getCheckIn()?->format('Y-m-d'),
            'checkOut' => $booking->getCheckOut()?->format('Y-m-d'),
            'guests'   => $booking->getGuestsCount(),
        ]);
    }

    /**
     * Step 2 — show checkout recap. GET only.
     */
    #[Route('/checkout/{id}', name: 'app_booking_checkout', methods: ['GET'])]
    public function checkout(Property $property, Request $request): Response
    {
        $checkInRaw  = $request->query->get('checkIn', '');
        $checkOutRaw = $request->query->get('checkOut', '');
        $guests      = $request->query->getInt('guests', 1);

        $checkIn  = \DateTimeImmutable::createFromFormat('Y-m-d', $checkInRaw) ?: null;
        $checkOut = \DateTimeImmutable::createFromFormat('Y-m-d', $checkOutRaw) ?: null;

        if (!$checkIn || !$checkOut || $checkOut <= $checkIn) {
            $this->addFlash('error', 'Dates invalides.');
            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        if ($guests < 1 || $guests > $property->getMaxGuests()) {
            $this->addFlash('error', 'Nombre de voyageurs invalide.');
            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        $nights     = $checkIn->diff($checkOut)->days;
        $totalPrice = $nights * (float) $property->getPricePerNight();

        return $this->render('booking/checkout.html.twig', [
            'property'   => $property,
            'checkIn'    => $checkIn,
            'checkOut'   => $checkOut,
            'guests'     => $guests,
            'nights'     => $nights,
            'totalPrice' => $totalPrice,
        ]);
    }

    /**
     * Step 3 — confirm and create the booking.
     */
    #[Route('/confirm/{id}', name: 'app_booking_confirm', methods: ['POST'])]
    public function confirm(Property $property, Request $request, BookingService $bookingService): Response
    {
        $checkInRaw  = $request->request->get('checkIn', '');
        $checkOutRaw = $request->request->get('checkOut', '');
        $guests      = (int) $request->request->get('guests', 1);

        $checkIn  = \DateTimeImmutable::createFromFormat('Y-m-d', $checkInRaw) ?: null;
        $checkOut = \DateTimeImmutable::createFromFormat('Y-m-d', $checkOutRaw) ?: null;

        if (!$checkIn || !$checkOut) {
            $this->addFlash('error', 'Données de réservation invalides.');
            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        try {
            $booking = $bookingService->create($property, $this->getUser(), $checkIn, $checkOut, $guests);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        $status = $booking->getStatus()->value === 'CONFIRMED'
            ? 'Votre réservation est confirmée !'
            : 'Votre demande a été envoyée à l\'hôte. Vous serez notifié par email.';

        $this->addFlash('success', $status);

        return $this->redirectToRoute('app_booking_confirmation', ['id' => $booking->getId()]);
    }

    #[Route('/{id}/confirmation', name: 'app_booking_confirmation')]
    public function confirmation(Booking $booking): Response
    {
        if ($booking->getTraveler() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('booking/confirmation.html.twig', ['booking' => $booking]);
    }

    #[Route('/{id}', name: 'app_booking_show', requirements: ['id' => '.+'])]
    public function show(Booking $booking): Response
    {
        if ($booking->getTraveler() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $cancelForm = $this->createForm(CancellationReasonType::class, null, [
            'action' => $this->generateUrl('app_booking_cancel', ['id' => $booking->getId()]),
            'method' => 'POST',
        ]);

        return $this->render('booking/show.html.twig', [
            'booking'     => $booking,
            'cancelForm'  => $cancelForm,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_booking_cancel', methods: ['POST'])]
    public function cancel(Booking $booking, Request $request, BookingService $bookingService): Response
    {
        if ($booking->getTraveler() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(CancellationReasonType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Veuillez indiquer un motif d\'annulation.');
            return $this->redirectToRoute('app_booking_show', ['id' => $booking->getId()]);
        }

        $reason = $form->get('reason')->getData();

        try {
            $bookingService->cancel($booking, $this->getUser(), $reason);
            $this->addFlash('success', 'Réservation annulée. Les deux parties ont été notifiées.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_booking_index');
    }
}
