<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Form\BookingType;
use App\Message\ExpireReservationMessage;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\PropertyVoter;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationAvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class BookingController extends AbstractController
{
    /**
     * JSON endpoint consumed by Flatpickr to disable already-booked ranges.
     * Public (no auth required) so the calendar loads even for non-connected visitors.
     */
    #[Route('/logement/{id}/disponibilite.json', name: 'app_booking_availability_json', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function availabilityJson(
        Property $property,
        ReservationRepository $reservationRepository,
    ): JsonResponse {
        $blocked = [];

        foreach ($reservationRepository->findBlockedRangesForProperty($property) as $range) {
            /** @var \DateTimeImmutable $checkin */
            $checkin = $range['checkin'];
            /** @var \DateTimeImmutable $checkout */
            $checkout = $range['checkout'];

            $blocked[] = [
                'from' => $checkin->format('Y-m-d\TH:i'),
                'to'   => $checkout->modify('+' . ReservationAvailabilityService::GAP_HOURS . ' hours')->format('Y-m-d\TH:i'),
            ];
        }

        return $this->json([
            'blocked'  => $blocked,
            'minDate'  => (new \DateTimeImmutable())->format('Y-m-d\TH:i'),
            'gapHours' => ReservationAvailabilityService::GAP_HOURS,
        ]);
    }

    #[Route('/logement/{id}/reserver', name: 'app_booking_checkout', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::VIEW, subject: 'property')]
    public function checkout(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
        EntityManagerInterface $entityManager,
        ReservationAvailabilityService $availabilityService,
        MailerInterface $mailer,
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

        $form = $this->createForm(BookingType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $checkinRaw  = (string) $form->get('checkinDate')->getData();
            $checkoutRaw = (string) $form->get('checkoutDate')->getData();

            $checkin  = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $checkinRaw)
                     ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i', $checkinRaw);
            $checkout = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $checkoutRaw)
                     ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i', $checkoutRaw);

            if (!$checkin || !$checkout) {
                $this->addFlash('error', 'Veuillez sélectionner des dates d\'arrivée et de départ valides.');

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ]);
            }

            $guestsCount = (int) ($form->getData()['guestsCount'] ?? 1);

            if ($guestsCount > $property->getMaxGuests()) {
                $this->addFlash('error', \sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests()));

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ]);
            }

            $errors = $availabilityService->getAvailabilityErrors($property, $checkin, $checkout, null, $user);
            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ]);
            }

            $pricing = $availabilityService->calculatePrice($property, $checkin, $checkout);
            $status = $property->isInstantBooking() ? 'confirmed' : 'pending';

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($user);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus($status);
            $reservation->setTotalPrice((string) $pricing['totalPrice']);
            $reservation->setCleaningFee($pricing['cleaningFee'] > 0 ? (string) $pricing['cleaningFee'] : null);
            $reservation->setServiceFee((string) $pricing['serviceFee']);
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus(null);
            $history->setNewStatus($status);
            $history->setChangedBy($user);
            $reservation->addStatusHistory($history);

            $entityManager->persist($reservation);
            $entityManager->flush();

            if ($status === 'pending') {
                $bus->dispatch(
                    new ExpireReservationMessage((string) $reservation->getId()),
                    [new DelayStamp(86400 * 1000)],
                );
            }

            $profileName = $user->getProfile()?->getFirstName() ?? $user->getEmail();
            $mailer->send(
                (new TemplatedEmail())
                    ->to($user->getEmail())
                    ->subject($status === 'confirmed' ? 'Réservation confirmée ✅' : 'Demande de réservation reçue ⏳')
                    ->htmlTemplate('emails/reservation_confirmation.html.twig')
                    ->context(['reservation' => $reservation, 'guest_name' => $profileName]),
            );

            $this->addFlash(
                $status === 'confirmed' ? 'success' : 'info',
                $status === 'confirmed'
                    ? \sprintf(
                        'Réservation confirmée pour "%s" — %d nuit(s), %.2f €. Un e-mail de confirmation vous a été envoyé.',
                        $property->getTitle(),
                        $pricing['nights'],
                        $pricing['totalPrice'],
                    )
                    : \sprintf(
                        'Demande de réservation envoyée pour "%s". Le propriétaire a 24h pour confirmer.',
                        $property->getTitle(),
                    ),
            );

            return $this->redirectToRoute('app_home');
        }

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }

    #[Route('/reservations/{id}/annuler', name: 'app_booking_cancel', methods: ['POST'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function cancel(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('cancel_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        if (!\in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            $this->addFlash('error', 'Cette réservation ne peut pas être annulée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $oldStatus = $reservation->getStatus();
        $reason = trim((string) $request->request->get('reason', ''));

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($oldStatus);
        $history->setNewStatus('cancelled');
        $history->setChangedBy($user);
        $reservation->addStatusHistory($history);

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason !== '' ? $reason : 'Annulée par le voyageur.');

        $entityManager->flush();

        $this->addFlash('success', 'Votre réservation a été annulée.');

        return $this->redirectToRoute('app_reservation_index');
    }
}
