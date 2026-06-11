<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\BookingType;
use App\Message\ReservationConfirmedMessage;
use App\Message\ReservationPendingMessage;
use App\Repository\PropertyAvailabilityRepository;
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
        ReservationRepository $reservationRepository,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
    ): Response {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;

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

        $bookedRanges = array_map(
            static fn (Reservation $r) => [
                'from' => $r->getCheckinDate()->format('Y-m-d'),
                'to'   => $r->getCheckoutDate()->format('Y-m-d'),
            ],
            $reservationRepository->findActiveForProperty($property),
        );

        $blockedDates = array_map(
            static fn (\App\Entity\PropertyAvailability $a) => $a->getAvailableDate()->format('Y-m-d'),
            $availabilityRepository->findBlockedDates($property),
        );

        $formData = [
            'checkinDate'  => $this->parseDateQuery($request->query->get('checkin')),
            'checkoutDate' => $this->parseDateQuery($request->query->get('checkout')),
        ];
        $form = $this->createForm(BookingType::class, $formData);
        $form->handleRequest($request);

        $renderBooking = fn () => $this->render('front/property/booking.html.twig', [
            'property'     => $property,
            'form'         => $form,
            'bookedRanges' => $bookedRanges,
            'blockedDates' => $blockedDates,
        ]);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $checkin = $data['checkinDate'];
            $checkout = $data['checkoutDate'];
            $guestsCount = (int) $data['guestsCount'];

            if ($checkin >= $checkout) {
                $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');

                return $renderBooking();
            }

            if ($guestsCount > $property->getMaxGuests()) {
                $this->addFlash('error', sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests()));

                return $renderBooking();
            }

            if ($reservationRepository->findOverlapping((string) $property->getId(), $checkin, $checkout)) {
                $this->addFlash('error', 'Ce logement est déjà réservé sur cette période.');

                return $renderBooking();
            }

            if ($availabilityRepository->hasBlockedDayInRange($property, $checkin, $checkout)) {
                $this->addFlash('error', 'Une ou plusieurs nuits de cette période sont indisponibles (bloquées par l\'hôte).');

                return $renderBooking();
            }

            $nights = (int) $checkin->diff($checkout)->days;
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
            $entityManager->flush();

            if ($reservation->getStatus() === 'pending') {
                $bus->dispatch(new ReservationPendingMessage((string) $reservation->getId()));
            } else {
                $bus->dispatch(new ReservationConfirmedMessage((string) $reservation->getId()));
            }

            $this->addFlash('success', 'Votre réservation a été enregistrée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $renderBooking();
    }

    private function parseDateQuery(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date : null;
    }
}
