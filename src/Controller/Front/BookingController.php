<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Form\BookingType;
use App\Message\ReservationConfirmedMessage;
use App\Message\ReservationPendingMessage;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
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
        ReservationService $reservationService,
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

        $bookedRanges = $reservationRepository->getBookedRanges($property);
        $form         = $this->createForm(BookingType::class);
        $form->handleRequest($request);

        $render = fn () => $this->render('front/property/booking.html.twig', [
            'property'     => $property,
            'form'         => $form,
            'bookedRanges' => $bookedRanges,
        ]);

        if ($form->isSubmitted() && $form->isValid()) {
            $data        = $form->getData();
            $checkin     = $data['checkinDate'];
            $checkout    = $data['checkoutDate'];
            $guestsCount = (int) $data['guestsCount'];

            if ($checkin >= $checkout) {
                $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');

                return $render();
            }

            if ($guestsCount > $property->getMaxGuests()) {
                $this->addFlash('error', sprintf('Ce logement accepte au maximum %d voyageurs.', $property->getMaxGuests()));

                return $render();
            }

            if (!$reservationService->isAvailable($property, $checkin, $checkout)) {
                $this->addFlash('error', 'Ce logement n\'est pas disponible pour ces dates.');

                return $render();
            }

            try {
                $reservation = $reservationService->book($property, $user, $checkin, $checkout, $guestsCount);
            } catch (\RuntimeException) {
                $this->addFlash('error', 'Ce logement vient d\'être réservé pour ces dates. Veuillez choisir d\'autres dates.');

                return $render();
            }

            if ($reservation->getStatus() === 'pending') {
                $bus->dispatch(new ReservationPendingMessage((string) $reservation->getId()));
            } else {
                $bus->dispatch(new ReservationConfirmedMessage((string) $reservation->getId()));
            }

            $this->addFlash('success', 'Votre réservation a été enregistrée.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $render();
    }
}
