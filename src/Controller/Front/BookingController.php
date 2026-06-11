<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Form\BookingType;
use App\Message\ReservationConfirmationNotification;
use App\Repository\PropertyRepository;
use App\Service\AvailabilityChecker;
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
        AvailabilityChecker $availabilityChecker,
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

        // Périodes indisponibles à afficher au voyageur (jours bloqués + séjours confirmés).
        $unavailablePeriods = $availabilityChecker->getUnavailablePeriods($property);

        $form = $this->createForm(BookingType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $checkin = $data['checkinDate'];
            $checkout = $data['checkoutDate'];
            $guestsCount = (int) $data['guestsCount'];

            // Algorithme de disponibilité (spécification A.2) :
            // logement publié + aucun jour bloqué + aucune réservation confirmée qui se superpose + capacité suffisante.
            $reasons = $availabilityChecker->getUnavailabilityReasons($property, $checkin, $checkout, $guestsCount);
            if ($reasons !== []) {
                foreach ($reasons as $reason) {
                    $this->addFlash('error', $reason);
                }

                // Statut 422 pour que Turbo affiche bien le formulaire ré-rendu (et les messages).
                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                    'unavailablePeriods' => $unavailablePeriods,
                ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $nights = (int) $checkin->diff($checkout)->days;
            $nightlyRate = (float) $property->getPricePerNight();
            $subtotal = $nightlyRate * $nights;
            $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
            $serviceFee = round($subtotal * 0.12, 2);
            $totalPrice = round($subtotal + $cleaningFee + $serviceFee, 2);

            // Réservation instantanée -> confirmée directement ; sinon en attente de validation par l'hôte.
            $initialStatus = $property->isInstantBooking() ? 'confirmed' : 'pending';

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($user);
            $reservation->setCheckinDate($checkin);
            $reservation->setCheckoutDate($checkout);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus($initialStatus);
            $reservation->setTotalPrice((string) $totalPrice);
            $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
            $reservation->setServiceFee((string) $serviceFee);
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
            $reservation->setCurrency('EUR');

            // Traçabilité du statut initial.
            $history = (new ReservationStatusHistory())
                ->setReservation($reservation)
                ->setOldStatus(null)
                ->setNewStatus($initialStatus)
                ->setChangedBy($user);
            $reservation->addStatusHistory($history);

            $entityManager->persist($reservation);
            $entityManager->persist($history);
            $entityManager->flush();

            // Déclenchement asynchrone de la notification email (traitée par le worker Messenger).
            $messageBus->dispatch(new ReservationConfirmationNotification((string) $reservation->getId()));

            $this->addFlash('success', 'Votre réservation a été enregistrée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        // Soumission invalide (erreurs de validation) -> 422 pour Turbo ; sinon affichage GET normal (200).
        $response = new Response('', $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
            'unavailablePeriods' => $unavailablePeriods,
        ], $response);
    }
}
