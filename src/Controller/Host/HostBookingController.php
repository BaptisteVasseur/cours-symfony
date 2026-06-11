<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Reservation;
use App\Entity\User;
use App\Form\RefusalType;
use App\Message\BookingStatusChangedMessage;
use App\Repository\ReservationRepository;
use App\Service\AvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\ReservationVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host')]
#[IsGranted('ROLE_HOST')]
final class HostBookingController extends AbstractController
{
    #[Route('/bookings', name: 'app_host_bookings', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('host/booking/index.html.twig', [
            'reservations' => $reservationRepository->findPendingForHost($user),
        ]);
    }

    #[Route('/bookings/{id}/accept', name: 'app_host_booking_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(
        Request $request,
        Reservation $reservation,
        AvailabilityService $availabilityService,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
    ): Response {
        if (!$this->isCsrfTokenValid('accept_reservation_'.$reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être acceptée.');

            return $this->redirectToRoute('app_host_bookings');
        }

        $property = $reservation->getProperty();
        $checkin = $reservation->getCheckinDate();
        $checkout = $reservation->getCheckoutDate();
        $guestsCount = $reservation->getGuestsCount();

        if (
            $property === null
            || $checkin === null
            || $checkout === null
            || $guestsCount === null
            || !$availabilityService->isAvailable($property, $checkin, $checkout, $guestsCount, $reservation)
        ) {
            $this->addFlash('error', 'Ces dates ne sont plus disponibles.');

            return $this->redirectToRoute('app_host_bookings');
        }

        $reservation->setStatus('confirmed');
        $entityManager->flush();

        $bus->dispatch(new BookingStatusChangedMessage((string) $reservation->getId()));

        $this->addFlash('success', 'Réservation acceptée.');

        return $this->redirectToRoute('app_host_bookings');
    }

    #[Route('/bookings/{id}/refuse', name: 'app_host_booking_refuse', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function refuse(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
    ): Response {
        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être refusée.');

            return $this->redirectToRoute('app_host_bookings');
        }

        $form = $this->createForm(RefusalType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Le motif du refus est obligatoire.');

            return $this->redirectToRoute('app_host_bookings');
        }

        $data = $form->getData();
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($data['cancellationReason']);
        $entityManager->flush();

        $bus->dispatch(new BookingStatusChangedMessage((string) $reservation->getId()));

        $this->addFlash('success', 'Réservation refusée.');

        return $this->redirectToRoute('app_host_bookings');
    }
}
