<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\BookingType;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Message\ReservationCreatedMessage;
use App\Security\Voter\PropertyVoter;
use Symfony\Component\Messenger\MessageBusInterface;
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

        $checkin  = $this->parseDate($request->query->get('checkin'));
        $checkout = $this->parseDate($request->query->get('checkout'));
        $guests   = max(1, min($property->getMaxGuests(), (int) ($request->query->get('guests') ?: 1)));

        $form = $this->createForm(BookingType::class, [
            'checkinDate'  => $checkin,
            'checkoutDate' => $checkout,
            'guestsCount'  => $guests,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data        = $form->getData();
            $checkin     = $data['checkinDate'];
            $checkout    = $data['checkoutDate'];
            $guestsCount = (int) $data['guestsCount'];

            if ($checkin >= $checkout) {
                $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form'     => $form,
                    'pricing'  => null,
                ]);
            }

            if ($guestsCount > $property->getMaxGuests()) {
                $this->addFlash('error', sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests()));

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form'     => $form,
                    'pricing'  => null,
                ]);
            }

            $pricing = $this->computePricing($property, $checkin, $checkout);

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($user);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus($property->isInstantBooking() ? 'confirmed' : 'pending');
            $reservation->setTotalPrice((string) $pricing['total']);
            $reservation->setCleaningFee($pricing['cleaningFee'] > 0 ? (string) $pricing['cleaningFee'] : null);
            $reservation->setServiceFee((string) $pricing['serviceFee']);
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            $entityManager->persist($reservation);

            $cursor = $checkin;
            while ($cursor < $checkout) {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setReservation($reservation);
                $availability->setOccupiedDate($cursor);
                $entityManager->persist($availability);
                $cursor = $cursor->modify('+1 day');
            }

            $entityManager->flush();

            $bus->dispatch(new ReservationCreatedMessage((string) $reservation->getId()));

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $pricing = ($checkin && $checkout && $checkout > $checkin)
            ? $this->computePricing($property, $checkin, $checkout)
            : null;

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form'     => $form,
            'pricing'  => $pricing,
        ]);
    }

    private function computePricing(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): array
    {
        $nights      = (int) $checkin->diff($checkout)->days;
        $nightlyRate = (float) $property->getPricePerNight();
        $subtotal    = $nightlyRate * $nights;
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee  = round($subtotal * 0.12, 2);
        $total       = round($subtotal + $cleaningFee + $serviceFee, 2);

        return [
            'nights'      => $nights,
            'nightlyRate' => $nightlyRate,
            'subtotal'    => $subtotal,
            'cleaningFee' => $cleaningFee,
            'serviceFee'  => $serviceFee,
            'total'       => $total,
        ];
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date : null;
    }
}
