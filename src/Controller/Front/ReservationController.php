<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\ReservationVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservations')]
#[IsGranted('ROLE_USER')]
final class ReservationController extends AbstractController
{
    #[Route('', name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/reservation/index.html.twig', [
            'reservations' => $reservationRepository->findByGuestForListing($user),
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function show(Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;

        return $this->render('front/reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_reservation_cancel', methods: ['POST'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function cancel(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('cancel_reservation_' . $reservation->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $reason = trim($request->request->getString('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif d\'annulation est obligatoire.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $user = $this->getUser();

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus($reservation->getStatus());
        $history->setNewStatus('cancelled');
        $history->setChangedBy($user);

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);

        $em->persist($history);
        $em->flush();

        $this->addFlash('success', 'Réservation annulée.');

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
    }
}
