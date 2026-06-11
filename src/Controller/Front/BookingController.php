<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Exception\UnavailableDatesException;
use App\Form\BookingType;
use App\Repository\PropertyRepository;
use App\Security\Voter\PropertyVoter;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\ReservationMailer;

#[IsGranted('ROLE_USER')]
final class BookingController extends AbstractController
{
    #[Route('/logement/{id}/reserver', name: 'app_booking_checkout', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::VIEW, subject: 'property')]
    public function checkout(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
        BookingService $bookingService,
        ReservationMailer $reservationMailer,
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

        $defaults = [];
        $checkinParam = $request->query->get('checkin');
        if ($checkinParam && ($d = \DateTime::createFromFormat('Y-m-d', $checkinParam))) {
            $defaults['checkinDate'] = $d->setTime(0, 0);
        }
        $checkoutParam = $request->query->get('checkout');
        if ($checkoutParam && ($d = \DateTime::createFromFormat('Y-m-d', $checkoutParam))) {
            $defaults['checkoutDate'] = $d->setTime(0, 0);
        }
        $guestsParam = $request->query->getInt('guests');
        if ($guestsParam > 0) {
            $defaults['guestsCount'] = $guestsParam;
        }
        $form = $this->createForm(BookingType::class, $defaults);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $checkin = \DateTimeImmutable::createFromInterface($data['checkinDate']);
            $checkout = \DateTimeImmutable::createFromInterface($data['checkoutDate']);
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
                $reservation = $bookingService->book($property, $user, $checkin, $checkout, $guestsCount);
            } catch (UnavailableDatesException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirectToRoute('app_booking_checkout', ['id' => $property->getId()]);
            }

            $reservationMailer->sendForNewReservation($reservation);

            $this->addFlash('success', 'confirmed' === $reservation->getStatus()
                ? 'Votre réservation est confirmée !'
                : 'Votre demande de réservation a bien été envoyée à l\'hôte.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }
}