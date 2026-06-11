<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Form\BookingType;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Message\ReservationCreatedMessage;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
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
        ReservationRepository $reservationRepository,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
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
                $reservation = $entityManager->wrapInTransaction(function () use (
                    $property, $user, $checkin, $checkout, $guestsCount,
                    $availabilityRepository, $reservationRepository, $entityManager,
                ) {
                    $entityManager->lock($property, LockMode::PESSIMISTIC_WRITE);

                    if ($availabilityRepository->hasBlockedDays($property, $checkin, $checkout)) {
                        throw new \RuntimeException('blocked');
                    }

                    if ($reservationRepository->hasOverlap($property, $checkin, $checkout)) {
                        throw new \RuntimeException('overlap');
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

                    $history = new ReservationStatusHistory();
                    $history->setReservation($reservation);
                    $history->setOldStatus(null);
                    $history->setNewStatus($reservation->getStatus());
                    $history->setChangedBy($user);

                    $entityManager->persist($reservation);
                    $entityManager->persist($history);
                    $entityManager->flush();

                    return $reservation;
                });
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'blocked') {
                    $this->addFlash('error', 'Ce logement est indisponible sur certaines dates de votre séjour.');
                } else {
                    $this->addFlash('error', 'Ce logement est déjà réservé sur ces dates.');
                }

                return $this->redirectToRoute('app_booking_checkout', ['id' => $property->getId()]);
            }

            $messageBus->dispatch(new ReservationCreatedMessage((string) $reservation->getId()));

            $this->addFlash('success', 'Votre réservation a été enregistrée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }
}
