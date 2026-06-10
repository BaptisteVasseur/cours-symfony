<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ReservationController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/reservations/overview', name: 'app_reservation_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/reservations.html.twig');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/reservations', name: 'app_booking_history', methods: ['GET'])]
    public function history(ReservationRepository $reservationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('booking/history.html.twig', [
            'bookings' => $reservationRepository->findByGuestWithProperty($user),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/booking/create/{id}', name: 'app_booking_create', methods: ['POST'])]
    public function create(
        string $id,
        Request $request,
        PropertyRepository $propertyRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $property = $propertyRepository->find($id) ?? throw $this->createNotFoundException('Listing not found.');

        $this->denyAccessUnlessGranted('BOOK_PROPERTY', $property);

        if (!$this->isCsrfTokenValid('book-' . $id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $startDate = new \DateTimeImmutable($request->getPayload()->getString('startDate'));
            $endDate = new \DateTimeImmutable($request->getPayload()->getString('endDate'));
        } catch (\Exception) {
            $this->addFlash('error', 'Dates invalides.');

            return $this->redirectToRoute('app_property_show', ['id' => $id]);
        }

        if ($endDate <= $startDate) {
            $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');

            return $this->redirectToRoute('app_property_show', ['id' => $id]);
        }

        $nights = (int) $startDate->diff($endDate)->days;
        $totalPrice = $nights * (float) $property->getPricePerNight();

        /** @var User $user */
        $user = $this->getUser();

        $reservation = new Reservation();
        $reservation
            ->setProperty($property)
            ->setGuest($user)
            ->setCheckinDate($startDate)
            ->setCheckoutDate($endDate)
            ->setGuestsCount(1)
            ->setStatus('pending')
            ->setTotalPrice(number_format($totalPrice, 2, '.', ''))
            ->setCurrency('EUR');

        $entityManager->persist($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Demande de réservation envoyée.');

        return $this->redirectToRoute('app_booking_confirm', ['id' => $reservation->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/reservations/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        return $this->render('home/reservation.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/booking/{id}/confirm', name: 'app_booking_confirm', methods: ['GET'])]
    public function confirm(Reservation $reservation): Response
    {
        if ($reservation->getGuest() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('booking/confirm.html.twig', [
            'booking' => $reservation,
        ]);
    }
}
