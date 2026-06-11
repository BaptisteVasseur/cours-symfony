<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReservationController extends AbstractController
{
    #[Route('/reservations', name: 'app_reservation_index')]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $reservations = $reservationRepository->findBy(
            ['guest' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/properties/{id}/book', name: 'app_reservation_book', methods: ['GET', 'POST'])]
    public function book(
        string $id,
        Request $request,
        PropertyRepository $propertyRepository,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $property = $propertyRepository->find($id);

        if (!$property) {
            throw $this->createNotFoundException('Propriété introuvable.');
        }

        if ($request->isMethod('POST')) {
            $checkinDate = \DateTimeImmutable::createFromFormat('Y-m-d', $request->request->get('checkinDate'));
            $checkoutDate = \DateTimeImmutable::createFromFormat('Y-m-d', $request->request->get('checkoutDate'));
            $guestsCount = (int) $request->request->get('guestsCount', 1);

            if (!$checkinDate || !$checkoutDate || $checkoutDate <= $checkinDate) {
                $this->addFlash('error', 'Les dates sélectionnées sont invalides.');
                return $this->redirectToRoute('app_reservation_book', ['id' => $id]);
            }

            if ($reservationRepository->findOverlapping($id, $checkinDate, $checkoutDate)) {
                $this->addFlash('error', 'Ce logement est déjà réservé sur cette période. Veuillez choisir d\'autres dates.');
                return $this->redirectToRoute('app_reservation_book', ['id' => $id]);
            }

            $nights = $checkinDate->diff($checkoutDate)->days;
            $totalPrice = (float) $property->getPricePerNight() * $nights;
            $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
            $serviceFee = round($totalPrice * 0.12, 2);

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($this->getUser());
            $reservation->setCheckinDate($checkinDate);
            $reservation->setCheckoutDate($checkoutDate);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus('pending');
            $reservation->setTotalPrice((string) ($totalPrice + $cleaningFee + $serviceFee));
            $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
            $reservation->setServiceFee((string) $serviceFee);
            $reservation->setCurrency('EUR');

            $entityManager->persist($reservation);
            $entityManager->flush();

            return $this->redirectToRoute('app_reservation_confirm', ['id' => $reservation->getId()]);
        }

        $bookedRanges = $reservationRepository->findBookedRanges($id);

        return $this->render('reservation/book.html.twig', [
            'property'     => $property,
            'bookedRanges' => $bookedRanges,
        ]);
    }

    #[Route('/reservations/{id}/confirm', name: 'app_reservation_confirm')]
    public function confirm(string $id, ReservationRepository $reservationRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $reservation = $reservationRepository->find($id);

        if (!$reservation || $reservation->getGuest() !== $this->getUser()) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        return $this->render('reservation/confirm.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}
