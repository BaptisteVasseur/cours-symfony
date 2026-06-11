<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\BookingType;
use App\Repository\PropertyRepository;
use App\Security\Voter\PropertyVoter;
use App\Service\BookingService;
use App\Service\ReservationWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class BookingController extends AbstractController
{
    private const CALENDAR_MONTHS_AHEAD = 12;

    #[Route('/logement/{id}/reserver', name: 'app_booking_checkout', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::VIEW, subject: 'property')]
    public function checkout(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
        EntityManagerInterface $entityManager,
        BookingService $bookingService,
        ReservationWorkflow $reservationWorkflow,
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
            $checkin = \DateTimeImmutable::createFromInterface($data['checkinDate'])->setTime(0, 0);
            $checkout = \DateTimeImmutable::createFromInterface($data['checkoutDate'])->setTime(0, 0);
            $guestsCount = (int) $data['guestsCount'];
            $today = new \DateTimeImmutable('today');

            $formError = match (true) {
                $checkin >= $checkout => 'La date de départ doit être postérieure à la date d\'arrivée.',
                $checkin < $today => 'La date d\'arrivée ne peut pas être dans le passé.',
                $guestsCount > $property->getMaxGuests() => sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests()),
                default => null,
            };
            if ($formError !== null) {
                $this->addFlash('error', $formError);

                return $this->renderCheckout($property, $form, $bookingService);
            }

            $quote = $bookingService->quote($property, $checkin, $checkout);

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($user);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setTotalPrice((string) $quote['total']);
            $reservation->setCleaningFee($quote['cleaningFee'] > 0 ? (string) $quote['cleaningFee'] : null);
            $reservation->setServiceFee((string) $quote['serviceFee']);
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            // Validation de la disponibilité en temps réel, juste avant la
            // redirection : si le créneau vient d'être pris, la demande est
            // créée annulée et le récapitulatif l'indique en flash.
            $unavailableReason = $bookingService->checkRangeAvailability($property, $checkin, $checkout, $guestsCount);
            if ($unavailableReason === null) {
                $reservation->setStatus('pending');
                $this->addFlash('success', 'Le logement est disponible pour ces dates : votre réservation est en attente.');
            } else {
                $reservation->setStatus('cancelled');
                $reservation->setCancellationReason('Logement indisponible au moment de la validation : ' . $unavailableReason);
                $this->addFlash('error', 'Le logement n\'est plus disponible pour cette période. ' . $unavailableReason);
            }

            $entityManager->persist($reservation);
            $entityManager->flush();

            // Notification asynchrone à l'hôte, après le flush (la donnée est
            // persistée, l'envoi ne bloque pas la requête).
            if ($reservation->getStatus() === 'pending') {
                $reservationWorkflow->notifyRequested($reservation);
            }

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->renderCheckout($property, $form, $bookingService);
    }

    private function renderCheckout(Property $property, mixed $form, BookingService $bookingService): Response
    {
        $today = new \DateTimeImmutable('today');
        $maxDate = $today->modify(sprintf('+%d months', self::CALENDAR_MONTHS_AHEAD));

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
            'unavailableNights' => $bookingService->unavailableNights($property, $today, $maxDate),
            'calendarMinDate' => $today->format('Y-m-d'),
            'calendarMaxDate' => $maxDate->format('Y-m-d'),
            'serviceFeeRate' => BookingService::SERVICE_FEE_RATE,
        ]);
    }
}
