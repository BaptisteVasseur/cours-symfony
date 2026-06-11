<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\BookingType;
use App\Message\ReservationConfirmedMessage;
use App\Message\ReservationPendingMessage;
use App\Repository\PropertyRepository;
use App\Service\AvailabilityService;
use App\Service\ReservationStatusService;
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
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly MessageBusInterface $bus,
        private readonly ReservationStatusService $statusService,
    ) {}

    #[Route('/logement/{id}/reserver', name: 'app_booking_checkout', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::VIEW, subject: 'property')]
    public function checkout(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
        EntityManagerInterface $entityManager,
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

        $checkinParam  = $request->query->get('checkin');
        $checkoutParam = $request->query->get('checkout');
        $guestsParam   = $request->query->getInt('guests', 1);

        $defaults = [];
        if ($checkinParam)  { $d = \DateTimeImmutable::createFromFormat('Y-m-d', $checkinParam);  if ($d) $defaults['checkinDate']  = $d; }
        if ($checkoutParam) { $d = \DateTimeImmutable::createFromFormat('Y-m-d', $checkoutParam); if ($d) $defaults['checkoutDate'] = $d; }
        if ($guestsParam > 0) $defaults['guestsCount'] = $guestsParam;

        $form = $this->createForm(BookingType::class, $defaults ?: null);
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
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            if ($guestsCount > $property->getMaxGuests()) {
                $this->addFlash('error', sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests()));

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            // Check availability before persisting
            if (!$this->availabilityService->isAvailable($property, $checkin, $checkout, $guestsCount)) {
                $this->addFlash('error', 'Ces dates ne sont plus disponibles. Veuillez choisir d\'autres dates.');

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
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

            $initialStatus = $property->isInstantBooking() ? 'confirmed' : 'pending';
            $entityManager->persist($reservation);
            $this->statusService->transition($reservation, $initialStatus, $user, null);
            $entityManager->flush();

            // Dispatch notification AFTER flush — reservation must exist before notifying
            if ($reservation->getStatus() === 'pending') {
                $this->bus->dispatch(new ReservationPendingMessage((string) $reservation->getId()));
            } else {
                $this->bus->dispatch(new ReservationConfirmedMessage((string) $reservation->getId()));
            }

            $this->addFlash('success', 'Votre réservation a été enregistrée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ], new Response(status: $status));
    }

}
