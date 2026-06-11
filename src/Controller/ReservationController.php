<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\ReservationRequestType;
use App\Repository\ReservationRepository;
use App\Service\Booking\BookingManager;
use App\Service\Booking\BookingPriceCalculator;
use Symfony\Component\Form\FormError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservations')]
final class ReservationController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('home/reservations.html.twig', [
            'reservations' => $reservationRepository->findForGuest($user),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/logement/{id}/new', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        Property $property,
        BookingManager $bookingManager,
        BookingPriceCalculator $priceCalculator,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ReservationRequestType::class, [
            'checkinDate' => $this->parseDate($request->query->get('checkin')),
            'checkoutDate' => $this->parseDate($request->query->get('checkout')),
            'guestsCount' => max(1, $request->query->getInt('guests', 1)),
        ]);
        $form->handleRequest($request);

        $priceBreakdown = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $checkin = $this->asImmutableDate($data['checkinDate'] ?? null);
            $checkout = $this->asImmutableDate($data['checkoutDate'] ?? null);
            $guests = (int) ($data['guestsCount'] ?? 1);

            try {
                $reservation = $bookingManager->book($property, $user, $checkin, $checkout, $guests);

                $this->addFlash(
                    'success',
                    $reservation->getStatus() === 'confirmed'
                        ? 'Votre réservation est confirmée.'
                        : 'Votre demande de réservation a été envoyée à l’hôte.',
                );

                return $this->redirectToRoute('app_reservation_show', [
                    'id' => $reservation->getId(),
                ], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        if (!$form->isSubmitted()) {
            $data = $form->getData();
            $checkin = $this->asImmutableDate($data['checkinDate'] ?? null, false);
            $checkout = $this->asImmutableDate($data['checkoutDate'] ?? null, false);

            if ($checkin !== null && $checkout !== null && $checkout > $checkin) {
                try {
                    $priceBreakdown = $priceCalculator->calculate($property, $checkin, $checkout);
                } catch (\DomainException) {
                    $priceBreakdown = null;
                }
            }
        }

        return $this->render('home/reservation_new.html.twig', [
            'property' => $property,
            'form' => $form,
            'priceBreakdown' => $priceBreakdown,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $reservation = $reservationRepository->findOneForGuestDetail($reservation, $user);
        if ($reservation === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('home/reservation.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date : null;
    }

    private function asImmutableDate(mixed $value, bool $required = true): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!$required) {
            return null;
        }

        throw new \DomainException('Les dates de séjour sont obligatoires.');
    }
}
