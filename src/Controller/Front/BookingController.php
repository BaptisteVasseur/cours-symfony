<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Exception\UnavailableDatesException;
use App\Form\BookingType;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
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
        ReservationService $reservationService,
        ReservationRepository $reservationRepository,
        PropertyAvailabilityRepository $availabilityRepository,
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

            if ($checkin >= $checkout) {
                $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');

                return $this->redirectToRoute('app_booking_checkout', ['id' => $property->getId()]);
            }

            if ($guestsCount > $property->getMaxGuests()) {
                $this->addFlash('error', sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests()));

                return $this->redirectToRoute('app_booking_checkout', ['id' => $property->getId()]);
            }

            try {
                $reservation = $reservationService->create($property, $user, $checkin, $checkout, $guestsCount);
            } catch (UnavailableDatesException) {
                $this->addFlash('error', 'Ces dates ne sont plus disponibles pour ce logement.');

                return $this->redirectToRoute('app_booking_checkout', ['id' => $property->getId()]);
            }

            $this->addFlash('success', 'Votre réservation a été enregistrée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $blockedDays = [];
        foreach ($availabilityRepository->findBlockedForProperty($property) as $avail) {
            $blockedDays[] = $avail->getAvailableDate()->format('Y-m-d');
        }

        $unavailableForCheckin = $blockedDays;
        $unavailableForCheckout = $blockedDays;

        foreach ($reservationRepository->findConfirmedForProperty($property) as $reservation) {
            $d = $reservation->getCheckinDate();
            while ($d < $reservation->getCheckoutDate()) {
                $unavailableForCheckin[] = $d->format('Y-m-d');
                // checkout on the checkin day of another reservation is valid [checkin, checkout)
                if ($d > $reservation->getCheckinDate()) {
                    $unavailableForCheckout[] = $d->format('Y-m-d');
                }
                $d = $d->modify('+1 day');
            }
        }

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
            'unavailableForCheckin' => array_values(array_unique($unavailableForCheckin)),
            'unavailableForCheckout' => array_values(array_unique($unavailableForCheckout)),
        ]);
    }
}
