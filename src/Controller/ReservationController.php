<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\AvailabilityService;
use App\Service\BookingException;
use App\Service\BookingService;
use App\Service\PricingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ReservationController extends AbstractController
{
    /**
     * Page d'historique : uniquement les réservations du voyageur connecté.
     */
    #[Route('/reservations', name: 'app_reservation_history')]
    #[IsGranted('ROLE_USER')]
    public function history(ReservationRepository $reservationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('pages/reservation/history.html.twig', [
            'reservations' => $reservationRepository->findHistory($user),
        ]);
    }

    /**
     * Récapitulatif (checkout) : validation de la plage et calcul du prix avant réservation.
     */
    #[Route('/reservation/checkout/{id}', name: 'app_reservation_checkout', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function checkout(
        Property $property,
        Request $request,
        AvailabilityService $availabilityService,
        PricingService $pricingService,
    ): Response {
        $dates = $this->parseDates($request);
        if ($dates === null) {
            $this->addFlash('error', 'Veuillez sélectionner des dates valides.');

            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        [$checkin, $checkout] = $dates;
        $guests = max(1, $request->query->getInt('guests', 1));

        $availability = $availabilityService->check($property, $checkin, $checkout, $guests);
        $price = $availability->available
            ? $pricingService->compute($property, $checkin, $checkout)
            : null;

        return $this->render('pages/reservation/checkout.html.twig', [
            'property' => $property,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'guests' => $guests,
            'availability' => $availability,
            'price' => $price,
        ]);
    }

    /**
     * Création de la réservation (statut confirmed ou pending selon le paramétrage du logement).
     */
    #[Route('/reservation/book/{id}', name: 'app_reservation_book', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function book(
        Property $property,
        Request $request,
        BookingService $bookingService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('book' . $property->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        $dates = $this->parseDates($request, $request->request);
        if ($dates === null) {
            $this->addFlash('error', 'Dates invalides.');

            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        [$checkin, $checkout] = $dates;
        $guests = max(1, $request->request->getInt('guests', 1));

        try {
            $reservation = $bookingService->create($property, $user, $checkin, $checkout, $guests);
        } catch (BookingException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_reservation_checkout', [
                'id' => $property->getId(),
                'checkin' => $checkin->format('Y-m-d'),
                'checkout' => $checkout->format('Y-m-d'),
                'guests' => $guests,
            ]);
        }

        $this->addFlash(
            'success',
            $reservation->getStatus() === Reservation::STATUS_CONFIRMED
                ? 'Réservation confirmée !'
                : 'Demande envoyée à l\'hôte. Vous serez notifié de sa réponse.',
        );

        return $this->redirectToRoute('app_reservation_confirmation', ['id' => $reservation->getId()]);
    }

    /**
     * Page de confirmation / suivi d'une réservation.
     */
    #[Route('/reservation/{id}/confirmation', name: 'app_reservation_confirmation')]
    #[IsGranted('ROLE_USER')]
    public function confirmation(Reservation $reservation): Response
    {
        $this->denyUnlessParticipant($reservation);

        return $this->render('pages/reservation/confirmation.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    /**
     * Annulation par le voyageur (motif obligatoire). Libère les dates.
     */
    #[Route('/reservation/{id}/cancel', name: 'app_reservation_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(
        Reservation $reservation,
        Request $request,
        BookingService $bookingService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($reservation->getGuest() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('cancel' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_reservation_confirmation', ['id' => $reservation->getId()]);
        }

        try {
            $bookingService->cancel($reservation, $user, (string) $request->request->get('reason'));
            $this->addFlash('success', 'Réservation annulée.');
        } catch (BookingException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_reservation_confirmation', ['id' => $reservation->getId()]);
    }

    /**
     * Parse checkin/checkout depuis la query (GET) ou le body (POST).
     *
     * @return array{0:\DateTimeImmutable, 1:\DateTimeImmutable}|null
     */
    private function parseDates(Request $request, ?\Symfony\Component\HttpFoundation\InputBag $bag = null): ?array
    {
        $bag ??= $request->query;
        $rawIn = (string) $bag->get('checkin');
        $rawOut = (string) $bag->get('checkout');
        if ($rawIn === '' || $rawOut === '') {
            return null;
        }

        try {
            $checkin = (new \DateTimeImmutable($rawIn))->setTime(0, 0, 0);
            $checkout = (new \DateTimeImmutable($rawOut))->setTime(0, 0, 0);
        } catch (\Exception) {
            return null;
        }

        return $checkout > $checkin ? [$checkin, $checkout] : null;
    }

    private function denyUnlessParticipant(Reservation $reservation): void
    {
        $user = $this->getUser();
        $isGuest = $reservation->getGuest() === $user;
        $isHost = $reservation->getProperty()?->getHost() === $user;
        if (!$isGuest && !$isHost) {
            throw $this->createAccessDeniedException();
        }
    }
}
