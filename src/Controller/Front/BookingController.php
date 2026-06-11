<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\BookingType;
use App\Repository\PropertyRepository;
use App\Security\Voter\PropertyVoter;
use App\Service\PropertyAvailabilityService;
use App\Service\ReservationStatusHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

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
        PropertyAvailabilityService $propertyAvailabilityService,
        ReservationStatusHistoryService $reservationStatusHistoryService,
        MailerInterface $mailer,
        Environment $twig,
        LoggerInterface $logger,
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

            return $this->redirectToRoute('app_reservation_index');
        }

        // Quick reservation flow: allow creating a reservation directly from the property page
        // when the listing form submits via GET with checkin/checkout/guests parameters.
        if ($request->isMethod('GET') && $request->query->get('checkin')) {
            $checkinStr = $request->query->get('checkin');
            $checkoutStr = $request->query->get('checkout');
            $guestsCount = (int) $request->query->get('guests', 1);

            try {
                $checkin = new \DateTimeImmutable($checkinStr);
                $checkout = new \DateTimeImmutable($checkoutStr);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Les dates sélectionnées sont invalides.');

                return $this->redirectToRoute('app_logement_detail', [
                    'id' => $property->getId(),
                    'checkin' => $checkinStr,
                    'checkout' => $checkoutStr,
                    'guests' => $guestsCount,
                ]);
            }

            if ($checkin >= $checkout) {
                $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');

                return $this->redirectToRoute('app_logement_detail', [
                    'id' => $property->getId(),
                    'checkin' => $checkinStr,
                    'checkout' => $checkoutStr,
                    'guests' => $guestsCount,
                ]);
            }

            if ($guestsCount > $property->getMaxGuests()) {
                $this->addFlash('error', sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests()));

                return $this->redirectToRoute('app_logement_detail', [
                    'id' => $property->getId(),
                    'checkin' => $checkinStr,
                    'checkout' => $checkoutStr,
                    'guests' => $guestsCount,
                ]);
            }

            if (!$propertyAvailabilityService->isAvailable($property, $checkin, $checkout, $guestsCount)) {
                $this->addFlash('error', 'Ce logement n\'est pas disponible pour ces dates.');

                return $this->redirectToRoute('app_logement_detail', [
                    'id' => $property->getId(),
                    'checkin' => $checkinStr,
                    'checkout' => $checkoutStr,
                    'guests' => $guestsCount,
                ]);
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
            $reservation->setStatus('pending');
            $reservation->setTotalPrice(number_format($totalPrice, 2, '.', ''));
            $reservation->setCleaningFee($cleaningFee > 0 ? number_format($cleaningFee, 2, '.', '') : null);
            $reservation->setServiceFee(number_format($serviceFee, 2, '.', ''));
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            $entityManager->persist($reservation);
            $reservationStatusHistoryService->record($reservation, null, 'pending', $user);
            $entityManager->flush();

            // send email to host
            $host = $property->getHost();
            if ($host?->getEmail()) {
                try {
                    $body = $twig->render('parts/reservation_pending_email.html.twig', [
                        'reservation' => $reservation,
                        'property' => $property,
                        'guest' => $user,
                    ]);
                    $email = (new Email())
                        ->from('noreply@example.com')
                        ->to($host->getEmail())
                        ->subject('Nouvelle demande de réservation')
                        ->html($body);
                    $mailer->send($email);
                    $logger->info('Reservation email sent (quick flow)', ['hostEmail' => $host->getEmail()]);
                } catch (\Throwable $e) {
                    $logger->error('Failed to send reservation email (quick flow)', [
                        'hostEmail' => $host->getEmail(),
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            if ($reservation->getStatus() === 'confirmed') {
                $this->addFlash('success', 'Votre réservation est confirmée.');
            } else {
                $this->addFlash('success', 'Votre demande de réservation a été envoyée à l\'hôte.');
            }

            return $this->redirectToRoute('app_reservation_index', ['id' => $reservation->getId()]);
        }

        $form = $this->createForm(BookingType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $checkin = $data['checkinDate'];
            $checkout = $data['checkoutDate'];
            $guestsCount = (int) $data['guestsCount'];

            if (!$checkin instanceof \DateTimeImmutable || !$checkout instanceof \DateTimeImmutable) {
                $this->addFlash('error', 'Les dates sélectionnées sont invalides.');

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ]);
            }

            if ($checkin >= $checkout) {
                $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ]);
            }

            if ($guestsCount > $property->getMaxGuests()) {
                $this->addFlash('error', sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests()));

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ]);
            }

            // Check if the property is published, free, has enought capacity, and that there are no conflicting reservations.
            if (!$propertyAvailabilityService->isAvailable($property, $checkin, $checkout, $guestsCount)) {
                $this->addFlash('error', 'Ce logement n\'est pas disponible pour ces dates.');

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ]);
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
            $reservation->setStatus('pending');
            $reservation->setTotalPrice(number_format($totalPrice, 2, '.', ''));
            $reservation->setCleaningFee($cleaningFee > 0 ? number_format($cleaningFee, 2, '.', '') : null);
            $reservation->setServiceFee(number_format($serviceFee, 2, '.', ''));
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            $entityManager->persist($reservation);
            $reservationStatusHistoryService->record($reservation, null, 'pending', $user);
            $entityManager->flush();

            // send email to host
            $host = $property->getHost();
            if ($host?->getEmail()) {
                try {
                    $body = $twig->render('parts/reservation_pending_email.html.twig', [
                        'reservation' => $reservation,
                        'property' => $property,
                        'guest' => $user,
                    ]);
                    $email = (new Email())
                        ->from('noreply@example.com')
                        ->to($host->getEmail())
                        ->subject('Nouvelle demande de réservation')
                        ->html($body);
                    $mailer->send($email);
                    $logger->info('Reservation email sent (form flow)', ['hostEmail' => $host->getEmail()]);
                } catch (\Throwable $e) {
                    $logger->error('Failed to send reservation email (form flow)', [
                        'hostEmail' => $host->getEmail(),
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            if ($reservation->getStatus() === 'confirmed') {
                $this->addFlash('success', 'Votre réservation est confirmée.');
            } else {
                $this->addFlash('success', 'Votre demande de réservation a été envoyée à l\'hôte.');
            }

            return $this->redirectToRoute('app_reservation_index', ['id' => $reservation->getId()]);
        }

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }
}
