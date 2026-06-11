<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Service\AvailabilityChecker;
use App\Service\BookingException;
use App\Service\BookingManager;
use App\Service\PriceCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservations')]
#[IsGranted('ROLE_USER')]
final class ReservationController extends AbstractController
{
    #[Route('', name: 'app_reservation_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/reservations.html.twig');
    }

    #[Route('/checkout/{id}', name: 'app_reservation_checkout', methods: ['GET', 'POST'])]
    public function checkout(
        Property $property,
        Request $request,
        AvailabilityChecker $availabilityChecker,
        PriceCalculator $priceCalculator,
        BookingManager $bookingManager,
    ): Response {
        $checkin = $this->parseDate($request->query->get('checkin') ?? $request->request->get('checkin'));
        $checkout = $this->parseDate($request->query->get('checkout') ?? $request->request->get('checkout'));
        $guests = max(1, (int) ($request->query->get('guests') ?? $request->request->get('guests') ?? 1));

        if ($checkin === null || $checkout === null || $checkout <= $checkin) {
            $this->addFlash('danger', 'Veuillez sélectionner des dates valides.');

            return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()]);
        }

        $available = $availabilityChecker->isAvailable($property, $checkin, $checkout, $guests);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('checkout'.$property->getId(), $request->getPayload()->getString('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide.');

                return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()]);
            }

            /** @var User $user */
            $user = $this->getUser();

            try {
                $reservation = $bookingManager->create($property, $user, $checkin, $checkout, $guests);
            } catch (BookingException $exception) {
                $this->addFlash('danger', $exception->getMessage());

                return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()]);
            }

            $this->addFlash('success', $reservation->getStatus() === 'confirmed'
                ? 'Réservation confirmée. Un email de confirmation vous a été envoyé.'
                : 'Demande envoyée à l\'hôte. Vous serez notifié de sa réponse.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('home/checkout.html.twig', [
            'property' => $property,
            'checkin' => $checkin->format('Y-m-d'),
            'checkout' => $checkout->format('Y-m-d'),
            'guests' => $guests,
            'available' => $available,
            'price' => $priceCalculator->compute($property, $checkin, $checkout),
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        $this->denyUnlessParticipant($reservation);

        return $this->render('home/reservation.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_reservation_cancel', methods: ['POST'])]
    public function cancel(Reservation $reservation, Request $request, BookingManager $bookingManager): Response
    {
        $this->denyUnlessParticipant($reservation);

        if (!$this->isCsrfTokenValid('cancel'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        /** @var User $user */
        $user = $this->getUser();
        $reason = trim($request->getPayload()->getString('reason'));

        try {
            $bookingManager->cancel($reservation, $user, $reason);
            $this->addFlash('success', 'Réservation annulée. Les deux parties ont été notifiées.');
        } catch (BookingException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false ? $date : null;
    }

    private function denyUnlessParticipant(Reservation $reservation): void
    {
        $user = $this->getUser();
        $isGuest = $reservation->getGuest() === $user;
        $isHost = $reservation->getProperty()?->getHost() === $user;

        if (!$isGuest && !$isHost && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
    }
}
