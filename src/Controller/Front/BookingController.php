<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Form\BookingType;
use App\Message\NewReservationRequestMessage;
use App\Message\ReservationConfirmedMessage;
use App\Repository\PropertyBlockedPeriodRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
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
        EntityManagerInterface $entityManager,
        ReservationRepository $reservationRepository,
        PropertyBlockedPeriodRepository $blockedPeriodRepository,
        MessageBusInterface $bus,
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

        $parseDate = static function (?string $v): ?\DateTimeImmutable {
            if ($v === null || $v === '') {
                return null;
            }
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $v);

            return $d !== false ? $d : null;
        };

        $preCheckin  = $parseDate($request->query->get('checkin'));
        $preCheckout = $parseDate($request->query->get('checkout'));
        $preGuests   = max(1, $request->query->getInt('guests', 1));

        $form = $this->createForm(BookingType::class, [
            'checkinDate'  => $preCheckin,
            'checkoutDate' => $preCheckout,
            'guestsCount'  => $preGuests,
        ]);
        $form->handleRequest($request);
        $bookedRanges   = $reservationRepository->findBookedRanges($property);
        $blockedPeriods = $blockedPeriodRepository->findAllForProperty($property);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $guestsCount = (int) $data['guestsCount'];

            // Combine selected date with property check-in/out times
            $checkinTime  = $property->getCheckinTime()  ?? new \DateTimeImmutable('15:00');
            $checkoutTime = $property->getCheckoutTime() ?? new \DateTimeImmutable('11:00');

            /** @var \DateTimeImmutable $checkin */
            $checkin = $data['checkinDate']->setTime(
                (int) $checkinTime->format('H'),
                (int) $checkinTime->format('i'),
            );
            /** @var \DateTimeImmutable $checkout */
            $checkout = $data['checkoutDate']->setTime(
                (int) $checkoutTime->format('H'),
                (int) $checkoutTime->format('i'),
            );

            if ($checkin >= $checkout) {
                $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');

                return $this->render('front/property/booking.html.twig', [
                    'property'       => $property,
                    'form'           => $form,
                    'bookedRanges'   => $bookedRanges,
                    'blockedPeriods' => $blockedPeriods,
                ]);
            }

            if ($guestsCount > $property->getMaxGuests()) {
                $this->addFlash('error', sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests()));

                return $this->render('front/property/booking.html.twig', [
                    'property'       => $property,
                    'form'           => $form,
                    'bookedRanges'   => $bookedRanges,
                    'blockedPeriods' => $blockedPeriods,
                ]);
            }

            if ($reservationRepository->hasConflict($property, $checkin, $checkout)) {
                $this->addFlash('error', 'Ce logement est déjà réservé sur ces dates.');

                return $this->render('front/property/booking.html.twig', [
                    'property'       => $property,
                    'form'           => $form,
                    'bookedRanges'   => $bookedRanges,
                    'blockedPeriods' => $blockedPeriods,
                ]);
            }

            if ($blockedPeriodRepository->hasConflict($property, $checkin, $checkout)) {
                $this->addFlash('error', 'Ce logement est indisponible sur ces dates (période bloquée par l\'hôte).');

                return $this->render('front/property/booking.html.twig', [
                    'property'       => $property,
                    'form'           => $form,
                    'bookedRanges'   => $bookedRanges,
                    'blockedPeriods' => $blockedPeriods,
                ]);
            }

            // Use date-only diff to count nights correctly regardless of intraday times
            $nights = (int) $checkin->setTime(0, 0, 0)->diff($checkout->setTime(0, 0, 0))->days;
            $nightlyRate = (float) $property->getPricePerNight();
            $subtotal = $nightlyRate * $nights;
            $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
            $serviceFee = round($subtotal * 0.12, 2);
            $totalPrice = round($subtotal + $cleaningFee + $serviceFee, 2);

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($user);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus($property->isInstantBooking() ? 'confirmed' : 'pending');
            $reservation->setTotalPrice((string) $totalPrice);
            $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
            $reservation->setServiceFee((string) $serviceFee);
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            $entityManager->persist($reservation);

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus(null);
            $history->setNewStatus($reservation->getStatus());
            $history->setChangedBy($user);
            $entityManager->persist($history);

            $entityManager->flush();

            if ($reservation->getStatus() === 'pending') {
                $bus->dispatch(new NewReservationRequestMessage((string) $reservation->getId()));
            } else {
                $bus->dispatch(new ReservationConfirmedMessage((string) $reservation->getId()));
            }

            $this->addFlash('success', 'Votre réservation a été enregistrée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('front/property/booking.html.twig', [
            'property'       => $property,
            'form'           => $form,
            'bookedRanges'   => $bookedRanges,
            'blockedPeriods' => $blockedPeriods,
        ]);
    }
}
