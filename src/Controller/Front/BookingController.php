<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Exception\BookingConflictException;
use App\Form\BookingType;
use App\Message\ReservationConfirmedNotification;
use App\Message\ReservationRequestedNotification;
use App\Repository\PropertyRepository;
use App\Service\Availability\AvailabilityChecker;
use App\Service\Reservation\PricingCalculator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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
        AvailabilityChecker $availabilityChecker,
        PricingCalculator $pricingCalculator,
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

            $result = $availabilityChecker->check($property, $checkin, $checkout, $guestsCount);
            if (!$result->available) {
                $form->addError(new FormError($result->firstReason() ?? 'Ces dates ne sont pas disponibles.'));

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ]);
            }

            try {
                $reservation = $entityManager->wrapInTransaction(
                    function (EntityManagerInterface $em) use ($property, $checkin, $checkout, $guestsCount, $user, $availabilityChecker, $pricingCalculator): Reservation {
                        $em->lock($property, LockMode::PESSIMISTIC_WRITE);

                        $locked = $availabilityChecker->check($property, $checkin, $checkout, $guestsCount);
                        if (!$locked->available) {
                            throw new BookingConflictException($locked->firstReason() ?? 'Ces dates ne sont plus disponibles.');
                        }

                        $price = $pricingCalculator->calculate($property, $checkin, $checkout);

                        $reservation = new Reservation();
                        $reservation->setProperty($property);
                        $reservation->setGuest($user);
                        $reservation->setCheckinDate($checkin);
                        $reservation->setCheckoutDate($checkout);
                        $reservation->setGuestsCount($guestsCount);
                        $reservation->setStatus($property->isInstantBooking() ? 'confirmed' : 'pending');
                        $reservation->setTotalPrice((string) $price->total);
                        $reservation->setCleaningFee($price->cleaningFee > 0 ? (string) $price->cleaningFee : null);
                        $reservation->setServiceFee((string) $price->serviceFee);
                        $reservation->setSecurityDeposit($property->getSecurityDeposit());
                        $reservation->setCurrency($price->currency);

                        $em->persist($reservation);

                        return $reservation;
                    }
                );
            } catch (BookingConflictException $e) {
                $form->addError(new FormError($e->getMessage()));

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ]);
            }

            $messageBus->dispatch(
                $reservation->getStatus() === 'confirmed'
                    ? new ReservationConfirmedNotification((string) $reservation->getId())
                    : new ReservationRequestedNotification((string) $reservation->getId()),
            );

            $this->addFlash(
                'success',
                $reservation->getStatus() === 'confirmed'
                    ? 'Votre réservation est confirmée.'
                    : 'Votre demande a été envoyée à l\'hôte pour validation.',
            );

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }
}
