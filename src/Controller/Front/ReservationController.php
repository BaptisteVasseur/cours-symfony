<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Form\CancellationReasonType;
use App\Message\ReservationCancelledMessage;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
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

    #[Route('/{id}/annuler', name: 'app_reservation_cancel', methods: ['GET', 'POST'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function cancel(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getGuest()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $form = $this->createForm(CancellationReasonType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reason    = $form->getData()['reason'];
            $oldStatus = $reservation->getStatus();

            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason($reason);

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus($oldStatus);
            $history->setNewStatus('cancelled');
            $history->setChangedBy($user);

            $entityManager->persist($history);
            $entityManager->flush();

            $bus->dispatch(new ReservationCancelledMessage(
                (string) $reservation->getId(),
                'le voyageur',
                $reason,
            ));

            $this->addFlash('success', 'Réservation annulée.');

            return $this->redirectToRoute('app_reservation_index');
        }

        return $this->render('front/reservation/cancel.html.twig', [
            'reservation' => $reservation,
            'form'        => $form,
        ]);
    }
}
