<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Exception\BookingConflictException;
use App\Exception\UnavailableDatesException;
use App\Form\BookingType;
use App\Repository\BookingRepository;
use App\Repository\PropertyRepository;
use App\Security\Voter\BookingVoter;
use App\Security\Voter\PropertyVoter;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class BookingController extends AbstractController
{
    #[Route('/booking/checkout', name: 'app_booking_checkout', methods: ['GET'])]
    #[Route('/logement/{id}/reserver', name: 'app_booking_checkout_legacy', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function checkout(
        Request $request,
        PropertyRepository $propertyRepository,
        ?Property $property = null,
    ): Response {
        $propertyId = $property?->getId() ?? $request->query->get('property_id') ?? $request->query->get('id');
        if (!$propertyId) {
            throw $this->createNotFoundException('Logement non spécifié.');
        }

        if (!$property) {
            $property = $propertyRepository->find($propertyId);
        }

        if (!$property) {
            throw $this->createNotFoundException('Logement introuvable.');
        }

        $this->denyAccessUnlessGranted(PropertyVoter::VIEW, $property);

        if ($property->getStatus() !== 'published') {
            throw $this->createNotFoundException('Ce logement n\'est pas disponible à la réservation.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($property->getHost()?->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas réserver votre propre logement.');
            return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()]);
        }

        $actionUrl = $request->attributes->get('_route') === 'app_booking_checkout_legacy'
            ? null
            : $this->generateUrl('app_booking_create', ['property_id' => $property->getId()]);

        $defaultData = [];
        $checkinVal = $request->query->get('checkin');
        $checkoutVal = $request->query->get('checkout');
        $guestsVal = $request->query->get('guests');

        if ($checkinVal) {
            $checkinDate = \DateTimeImmutable::createFromFormat('Y-m-d', $checkinVal);
            if ($checkinDate) {
                $defaultData['checkinDate'] = $checkinDate;
            }
        }
        if ($checkoutVal) {
            $checkoutDate = \DateTimeImmutable::createFromFormat('Y-m-d', $checkoutVal);
            if ($checkoutDate) {
                $defaultData['checkoutDate'] = $checkoutDate;
            }
        }
        if ($guestsVal !== null && $guestsVal !== '') {
            $defaultData['guestsCount'] = (int) $guestsVal;
        }

        $form = $this->createForm(BookingType::class, $defaultData, array_filter([
            'action' => $actionUrl,
            'method' => 'POST',
        ]));

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }

    #[Route('/booking', name: 'app_booking_create', methods: ['POST'])]
    #[Route('/logement/{id}/reserver', name: 'app_booking_create_legacy', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        Request $request,
        PropertyRepository $propertyRepository,
        BookingService $bookingService,
        ?Property $property = null,
    ): Response {
        $propertyId = $property?->getId() ?? $request->query->get('property_id') ?? $request->request->get('property_id') ?? $request->query->get('id');
        if (!$propertyId) {
            throw $this->createNotFoundException('Logement non spécifié.');
        }

        if (!$property) {
            $property = $propertyRepository->find($propertyId);
        }

        if (!$property) {
            throw $this->createNotFoundException('Logement introuvable.');
        }

        $this->denyAccessUnlessGranted(PropertyVoter::VIEW, $property);

        if ($property->getStatus() !== 'published') {
            throw $this->createNotFoundException('Ce logement n\'est pas disponible à la réservation.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($property->getHost()?->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas réserver votre propre logement.');
            return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()]);
        }

        $form = $this->createForm(BookingType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $checkin = $data['checkinDate'];
            $checkout = $data['checkoutDate'];
            $guestsCount = (int) $data['guestsCount'];

            try {
                $reservation = $bookingService->create($property, $user, $checkin, $checkout, $guestsCount);
            } catch (BookingConflictException|UnavailableDatesException|\LogicException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $message = $reservation->getStatus() === 'confirmed'
                ? 'Votre réservation est confirmée.'
                : 'Votre demande de réservation a été transmise à l’hôte.';
            $this->addFlash('success', $message);

            return $this->redirectToRoute('app_booking_show', ['id' => $reservation->getId()]);
        }

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/booking/{id}', name: 'app_booking_show', methods: ['GET'])]
    #[IsGranted(BookingVoter::VIEW, subject: 'booking')]
    public function show(
        Reservation $booking,
        BookingRepository $bookingRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $booking = $bookingRepository->findOneForDetail($booking) ?? $booking;

        return $this->render('front/reservation/show.html.twig', [
            'reservation' => $booking,
        ]);
    }

    #[Route('/booking/{id}/cancel', name: 'app_booking_cancel', methods: ['POST'])]
    #[IsGranted(BookingVoter::CANCEL, subject: 'booking')]
    public function cancel(
        Request $request,
        Reservation $booking,
        BookingService $bookingService,
    ): Response {
        if (!$this->isCsrfTokenValid('cancel_guest'.$booking->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_booking_show', ['id' => $booking->getId()]);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reason = trim((string) $request->request->get('cancellation_reason'));

        try {
            $bookingService->cancel($booking, $user, $reason);
            $this->addFlash('success', 'La réservation a été annulée.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_booking_show', ['id' => $booking->getId()]);
    }
}
