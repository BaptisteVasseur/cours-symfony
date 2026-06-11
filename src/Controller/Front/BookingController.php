<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Form\BookingType;
use App\Repository\PropertyRepository;
use App\Service\BookingAvailabilityResult;
use App\Service\BookingAvailabilityService;
use App\Service\ReservationWorkflowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\PropertyVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class BookingController extends AbstractController
{
    #[Route('/logement/{id}/reserver', name: 'app_booking_checkout', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::VIEW, subject: 'property')]
    public function checkout(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
        BookingAvailabilityService $bookingAvailabilityService,
        ReservationWorkflowService $reservationWorkflowService,
    ): Response {
        if ($property->getStatus() !== 'published') {
            throw $this->createNotFoundException('Ce logement n\'est pas disponible à la réservation.');
        }

        $property = $propertyRepository->findOneForDetail($property) ?? $property;
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

            $availability = $bookingAvailabilityService->check($property, $checkin, $checkout, $guestsCount);
            if (!$availability->isAvailable()) {
                $this->addFlash('error', $this->availabilityErrorMessage($availability, $property));

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ]);
            }

            try {
                $reservation = $reservationWorkflowService->createReservation(
                    $property,
                    $user,
                    $checkin,
                    $checkout,
                    $guestsCount,
                );
            } catch (\DomainException $exception) {
                $this->addFlash('error', $this->availabilityErrorMessageFromCode($exception->getMessage(), $property));

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ]);
            }

            $this->addFlash(
                'success',
                $reservation->getStatus() === 'confirmed'
                    ? 'Votre réservation est confirmée.'
                    : 'Votre demande de réservation a été envoyée à l’hôte.',
            );

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }

    private function availabilityErrorMessage(BookingAvailabilityResult $availability, Property $property): string
    {
        return $this->availabilityErrorMessageFromCode($availability->getPrimaryReasonCode(), $property);
    }

    private function availabilityErrorMessageFromCode(?string $reasonCode, Property $property): string
    {
        return match ($reasonCode) {
            BookingAvailabilityService::INVALID_DATE_RANGE => 'La date de départ doit être postérieure à la date d\'arrivée.',
            BookingAvailabilityService::GUEST_CAPACITY_EXCEEDED => sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests()),
            BookingAvailabilityService::DATES_ALREADY_BOOKED => 'Ce logement n\'est plus disponible sur les dates sélectionnées.',
            BookingAvailabilityService::DATES_MANUALLY_BLOCKED => 'Ce logement est indisponible sur les dates sélectionnées.',
            BookingAvailabilityService::MINIMUM_STAY_NOT_MET => 'La durée sélectionnée ne respecte pas le séjour minimum demandé pour cette période.',
            BookingAvailabilityService::PROPERTY_NOT_PUBLISHED => 'Ce logement n\'est pas disponible à la réservation.',
            default => 'La réservation ne peut pas être effectuée pour les dates sélectionnées.',
        };
    }
}
