<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\BookingType;
use App\Message\BookingConfirmedMessage;
use App\Message\BookingRequestMessage;
use App\Message\CheckinReminderMessage;
use App\Message\ExpireBookingMessage;
use App\Service\NotificationService;
use App\Repository\PropertyRepository;
use App\Security\Voter\PropertyVoter;
use App\Service\AvailabilityService;
use App\Service\ReservationWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Attribute\Route;
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
        AvailabilityService $availabilityService,
        ReservationWorkflowService $workflowService,
        MessageBusInterface $bus,
        NotificationService $notificationService,
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

        $checkinDefault  = null;
        $checkoutDefault = null;
        $guestsDefault   = 1;

        if ($request->isMethod('GET')) {
            $checkinStr  = $request->query->get('checkin', '');
            $checkoutStr = $request->query->get('checkout', '');
            $guestsParam = $request->query->getInt('guests', 0);
            try {
                if ($checkinStr !== '') {
                    $checkinDefault = new \DateTimeImmutable($checkinStr);
                }
                if ($checkoutStr !== '') {
                    $checkoutDefault = new \DateTimeImmutable($checkoutStr);
                }
            } catch (\Exception) {}
            if ($guestsParam > 0) {
                $guestsDefault = $guestsParam;
            }
        }

        $form = $this->createForm(BookingType::class, [
            'checkinDate'  => $checkinDefault,
            'checkoutDate' => $checkoutDefault,
            'guestsCount'  => $guestsDefault,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $checkin = $data['checkinDate'];
            $checkout = $data['checkoutDate'];
            $guestsCount = (int) $data['guestsCount'];

            if ($checkin >= $checkout) {
                $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            if (!$availabilityService->isAvailable($property, $checkin, $checkout, $guestsCount)) {
                $this->addFlash('error', 'Ce logement n\'est pas disponible pour les dates ou le nombre de voyageurs sélectionnés.');

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
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
            $reservation->setTotalPrice((string) $totalPrice);
            $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
            $reservation->setServiceFee((string) $serviceFee);
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            $entityManager->persist($reservation);

            $initialStatus = $property->isInstantBooking() ? 'confirmed' : 'pending';
            $workflowService->transition($reservation, $initialStatus, $user);

            if ($property->isInstantBooking()) {
                $notificationService->notifyBookingConfirmed($reservation);
            } else {
                $notificationService->notifyBookingRequest($reservation);
            }

            $entityManager->flush();

            if ($property->isInstantBooking()) {
                $bus->dispatch(new BookingConfirmedMessage((string) $reservation->getId()));
                self::dispatchCheckinReminder($bus, $reservation);
                $this->addFlash('success', 'Réservation confirmée ! Vous recevrez une confirmation par email.');
            } else {
                $bus->dispatch(new BookingRequestMessage((string) $reservation->getId()));
                $bus->dispatch(
                    new ExpireBookingMessage((string) $reservation->getId()),
                    [new DelayStamp(24 * 60 * 60 * 1000)]
                );
                $this->addFlash('success', 'Demande envoyée ! L\'hôte doit valider votre réservation.');
            }

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ], $form->isSubmitted() ? new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY) : new Response());
    }

    public static function dispatchCheckinReminder(MessageBusInterface $bus, Reservation $reservation): void
    {
        $checkin = $reservation->getCheckinDate();
        if ($checkin === null) {
            return;
        }

        $reminderAt = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $checkin->format('Y-m-d').' 09:00:00',
            new \DateTimeZone('Europe/Paris')
        );

        if ($reminderAt === false) {
            return;
        }

        $reminderAt = $reminderAt->modify('-1 day');
        $delayMs    = (int) (($reminderAt->getTimestamp() - time()) * 1000);

        if ($delayMs > 0) {
            $bus->dispatch(
                new CheckinReminderMessage((string) $reservation->getId()),
                [new DelayStamp($delayMs)]
            );
        }
    }
}
