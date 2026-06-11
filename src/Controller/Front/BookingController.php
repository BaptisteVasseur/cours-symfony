<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Form\BookingType;
use App\Message\BookingConfirmedMessage;
use App\Message\BookingRequestedMessage;
use App\Repository\PropertyRepository;
use App\Service\AvailabilityService;
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
        AvailabilityService $availabilityService,
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
            $data = $form->getData();
            $checkin = \DateTimeImmutable::createFromInterface($data['checkinDate']);
            $checkout = \DateTimeImmutable::createFromInterface($data['checkoutDate']);
            $guestsCount = (int) $data['guestsCount'];

            if ($checkin >= $checkout) {
                $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ], new Response(null, 422));
            }

            if (!$availabilityService->isAvailable($property, $checkin, $checkout, $guestsCount)) {
                $this->addFlash('error', 'Ce logement n\'est pas disponible pour les dates ou le nombre de voyageurs sélectionnés.');

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ], new Response(null, 422));
            }

            $nights = (int) $checkin->diff($checkout)->days;
            $nightlyRate = (float) $property->getPricePerNight();
            $subtotal = $nightlyRate * $nights;
            $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
            $serviceFee = round($subtotal * 0.12, 2);
            $totalPrice = round($subtotal + $cleaningFee + $serviceFee, 2);

            $status = $property->isInstantBooking() ? 'confirmed' : 'pending';

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($user);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus($status);
            $reservation->setTotalPrice((string) $totalPrice);
            $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
            $reservation->setServiceFee((string) $serviceFee);
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setNewStatus($status);
            $history->setChangedBy($user);
            $reservation->addStatusHistory($history);

            $entityManager->persist($reservation);
            $entityManager->persist($history);
            $entityManager->flush();

            $reservationId = (string) $reservation->getId();

            if ($status === 'confirmed') {
                $bus->dispatch(new BookingConfirmedMessage($reservationId));
                $this->addFlash('success', 'Votre réservation est confirmée !');
            } else {
                $bus->dispatch(new BookingRequestedMessage($reservationId));
                $this->addFlash('success', 'Votre demande a été envoyée à l\'hôte. Vous recevrez une réponse prochainement.');
            }

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservationId]);
        }

        $status = $form->isSubmitted() && !$form->isValid() ? 422 : 200;

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ], new Response(null, $status));
    }
}
