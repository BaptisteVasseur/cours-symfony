<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/reservations')]
#[IsGranted('ROLE_HOST')]
final class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_reservations', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reservations = $reservationRepository->findByHostForListing($user);

        $pending   = array_filter($reservations, fn (Reservation $r) => $r->getStatus() === 'pending');
        $confirmed = array_filter($reservations, fn (Reservation $r) => $r->getStatus() === 'confirmed');
        $others    = array_filter($reservations, fn (Reservation $r) => !in_array($r->getStatus(), ['pending', 'confirmed'], true));

        return $this->render('front/host/reservation/index.html.twig', [
            'pending'   => array_values($pending),
            'confirmed' => array_values($confirmed),
            'others'    => array_values($others),
        ]);
    }

    #[Route('/{id}', name: 'app_host_reservation_show', methods: ['GET'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function show(Reservation $reservation): Response
    {
        return $this->render('front/host/reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/confirmer', name: 'app_host_reservation_confirm', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function confirm(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('host_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->getId()]);
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Seules les réservations en attente peuvent être confirmées.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->getId()]);
        }

        $user = $this->getUser();

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('confirmed');
        $history->setChangedBy($user instanceof User ? $user : null);
        $reservation->addStatusHistory($history);
        $reservation->setStatus('confirmed');

        $entityManager->flush();

        $guestName = $reservation->getGuest()?->getProfile()?->getFirstName()
            ?? $reservation->getGuest()?->getEmail()
            ?? 'le voyageur';

        $this->addFlash('success', sprintf(
            'Réservation de %s confirmée pour "%s".',
            $guestName,
            $reservation->getProperty()?->getTitle() ?? '',
        ));

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_refuse', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function refuse(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('host_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->getId()]);
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Seules les réservations en attente peuvent être refusées.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->getId()]);
        }

        $user = $this->getUser();
        $reason = trim((string) $request->request->get('reason', ''));

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('cancelled');
        $history->setChangedBy($user instanceof User ? $user : null);
        $reservation->addStatusHistory($history);
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason !== '' ? $reason : 'Refusée par le propriétaire.');

        $entityManager->flush();

        $this->addFlash('info', sprintf(
            'Demande de %s refusée.',
            $reservation->getGuest()?->getProfile()?->getFirstName()
                ?? $reservation->getGuest()?->getEmail()
                ?? 'le voyageur',
        ));

        return $this->redirectToRoute('app_host_reservations');
    }
}
