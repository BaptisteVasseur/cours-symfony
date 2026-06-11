<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyBlock;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\PropertyBlockType;
use App\Message\ReservationCancelledMessage;
use App\Message\ReservationConfirmedMessage;
use App\Repository\PropertyBlockRepository;
use App\Repository\ReservationRepository;
use App\Service\AvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote')]
#[IsGranted('ROLE_USER')]
final class HostController extends AbstractController
{
    #[Route('/logement/{id}/calendrier', name: 'app_host_calendar', methods: ['GET', 'POST'])]
    public function calendar(
        Property $property,
        Request $request,
        PropertyBlockRepository $blockRepository,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $block = new PropertyBlock();
        $block->setProperty($property);
        $form = $this->createForm(PropertyBlockType::class, $block);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($block);
            $em->flush();
            $this->addFlash('success', 'Période bloquée.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $month = max(1, min(12, (int) $request->query->get('month', date('n'))));
        $year = max(2020, (int) $request->query->get('year', date('Y')));

        return $this->render('front/host/calendar.html.twig', [
            'property' => $property,
            'form' => $form,
            'blocks' => $blockRepository->findAllForProperty($property),
            'reservations' => $reservationRepository->findConfirmedForProperty($property),
            'month' => $month,
            'year' => $year,
        ]);
    }

    #[Route('/logement/{id}/bloquer/{blockId}/supprimer', name: 'app_host_block_delete', methods: ['POST'])]
    public function deleteBlock(
        Property $property,
        string $blockId,
        PropertyBlockRepository $blockRepository,
        EntityManagerInterface $em,
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete_block_'.$blockId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $block = $blockRepository->find($blockId);
        if ($block !== null && $block->getProperty() === $property) {
            $em->remove($block);
            $em->flush();
            $this->addFlash('success', 'Blocage supprimé.');
        }

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/logement/{id}/calendrier/token/generer', name: 'app_host_calendar_token_generate', methods: ['POST'])]
    public function generateCalendarToken(
        Property $property,
        EntityManagerInterface $em,
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('calendar_token_'.$property->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $property->setCalendarToken(bin2hex(random_bytes(32)));
        $em->flush();
        $this->addFlash('success', 'Lien iCal généré.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/logement/{id}/calendrier/token/revoquer', name: 'app_host_calendar_token_revoke', methods: ['POST'])]
    public function revokeCalendarToken(
        Property $property,
        EntityManagerInterface $em,
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('calendar_token_'.$property->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $property->setCalendarToken(null);
        $em->flush();
        $this->addFlash('success', 'Lien iCal révoqué.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/reservations', name: 'app_host_reservations', methods: ['GET'])]
    public function reservations(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservations.html.twig', [
            'pendingReservations' => $reservationRepository->findPendingForHost($user),
            'upcomingReservations' => $reservationRepository->findConfirmedForHost($user),
        ]);
    }

    #[Route('/reservations/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    public function acceptReservation(
        Reservation $reservation,
        EntityManagerInterface $em,
        Request $request,
        AvailabilityService $availabilityService,
        MessageBusInterface $bus,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $reservation->getProperty()->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation n\'est pas en attente.');

            return $this->redirectToRoute('app_host_reservations');
        }

        if (!$this->isCsrfTokenValid('accept_'.$reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($availabilityService->isAvailable(
            $reservation->getProperty(),
            $reservation->getCheckinDate(),
            $reservation->getCheckoutDate(),
            $reservation->getGuestsCount(),
        )) {
            $reservation->setStatus('confirmed');
            $em->flush();
            $bus->dispatch(new ReservationConfirmedMessage((string) $reservation->getId()));
            $this->addFlash('success', 'Réservation confirmée.');
        } else {
            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason('Dates plus disponibles au moment de la validation.');
            $em->flush();
            $bus->dispatch(new ReservationCancelledMessage((string) $reservation->getId(), 'host'));
            $this->addFlash('error', 'Les dates ne sont plus disponibles. La demande a été annulée automatiquement.');
        }

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/reservations/{id}/refuser', name: 'app_host_reservation_refuse', methods: ['POST'])]
    public function refuseReservation(
        Reservation $reservation,
        EntityManagerInterface $em,
        Request $request,
        MessageBusInterface $bus,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $reservation->getProperty()->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation n\'est pas en attente.');

            return $this->redirectToRoute('app_host_reservations');
        }

        $reason = trim((string) $request->request->get('cancellationReason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_reservations');
        }

        if (!$this->isCsrfTokenValid('refuse_'.$reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $em->flush();

        $bus->dispatch(new ReservationCancelledMessage((string) $reservation->getId(), 'host'));
        $this->addFlash('success', 'Réservation refusée.');

        return $this->redirectToRoute('app_host_reservations');
    }
}
