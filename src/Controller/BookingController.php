<?php

namespace App\Controller;

use App\Dto\BookingRequestDto;
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
     * Step 1 — validate form data from the property page, then redirect to checkout recap.
     */
    #[Route('/new/{id}', name: 'app_booking_new', methods: ['POST'])]
    public function new(Property $property, Request $request): Response
    {
        $booking = new Booking();
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
        $dto = BookingRequestDto::fromRawValues(
            $request->query->get('checkIn', ''),
            $request->query->get('checkOut', ''),
            $request->query->getInt('guests', 1),
        );

        if (!$dto) {
            $this->addFlash('error', 'Dates ou nombre de voyageurs invalides.');
            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        if ($dto->guestsCount > $property->getMaxGuests()) {
            $this->addFlash('error', sprintf(
                'Ce logement accueille au maximum %d voyageur(s).',
                $property->getMaxGuests()
            ));
            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        return $this->render('booking/checkout.html.twig', [
            'property'   => $property,
            'booking'    => $dto,
            'totalPrice' => $dto->computeTotal($property->getPricePerNight()),
        ]);
    }

    /**
     * Step 3 — confirm and create the booking.
     */
    #[Route('/confirm/{id}', name: 'app_booking_confirm', methods: ['POST'])]
    public function confirm(Property $property, Request $request, BookingService $bookingService): Response
    {
        $dto = BookingRequestDto::fromRawValues(
            $request->request->get('checkIn', ''),
            $request->request->get('checkOut', ''),
            (int) $request->request->get('guests', 1),
        );

        if (!$dto) {
            $this->addFlash('error', 'Données de réservation invalides.');
            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        try {
            $booking = $bookingService->create($property, $this->getUser(), $dto->checkIn, $dto->checkOut, $dto->guestsCount);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        $message = $booking->getStatus()->value === 'CONFIRMED'
            ? 'Votre réservation est confirmée !'
            : 'Votre demande a été envoyée à l\'hôte. Vous serez notifié par email.';

        $this->addFlash('success', $message);

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

    #[Route('/{id}', name: 'app_booking_show')]
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
            'booking'    => $booking,
            'cancelForm' => $cancelForm,
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

        try {
            $bookingService->cancel($booking, $this->getUser(), $form->get('reason')->getData());
            $this->addFlash('success', 'Réservation annulée. Les deux parties ont été notifiées.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_booking_index');
    }
}
