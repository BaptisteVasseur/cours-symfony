<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\ReservationStatut;
use App\Repository\ReservationRepository;
use App\Service\DisponibiliteService;
use App\Service\NotificationService;
use App\Service\ReservationEmailNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/reservations')]
#[IsGranted('ROLE_HOTE')]
class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservations): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->render('host/reservation/index.html.twig', [
            'reservations' => $reservations->trouverPourHote($user),
        ]);
    }

    #[Route('/{id}', name: 'app_host_reservation_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        $this->verifierAccesHote($reservation);

        return $this->render('host/reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_reservation_accept', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function accept(Reservation $reservation, Request $request, EntityManagerInterface $entityManager, NotificationService $notificationService, DisponibiliteService $disponibilites, ReservationEmailNotifier $emailNotifier): RedirectResponse
    {
        $this->verifierAccesHote($reservation);

        if (!$this->isCsrfTokenValid('host_reservation_accept_'.$reservation->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expiree. Reessayez.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
        }

        if ($reservation->statut !== ReservationStatut::EN_ATTENTE_HOTE) {
            $this->addFlash('error', 'Cette reservation ne peut plus etre acceptee.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
        }

        if (!$disponibilites->estDisponible($reservation->logement, $reservation->dateArrivee, $reservation->dateDepart, $reservation)) {
            $this->addFlash('error', 'Ces dates ne sont plus disponibles. La demande ne peut pas etre acceptee.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
        }

        $reservation->statut = ReservationStatut::ACCEPTEE_EN_ATTENTE_PAIEMENT;
        $reservation->dateAcceptation = new \DateTimeImmutable();
        $reservation->dateExpirationPaiement = new \DateTimeImmutable('+48 hours');

        $notificationService->creer(
            $reservation->voyageur,
            'reservation_acceptee',
            'Demande acceptee',
            sprintf('Votre demande pour %s a ete acceptee. Vous pouvez maintenant proceder au paiement.', $reservation->logement->titre),
            $this->generateUrl('app_reservation_show', ['id' => $reservation->id]),
        );
        $entityManager->flush();
        $emailNotifier->reservationAcceptee($reservation);

        $this->addFlash('success', 'Demande acceptee. Le voyageur doit maintenant payer pour confirmer la reservation.');

        return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_refuse', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function refuse(Reservation $reservation, Request $request, EntityManagerInterface $entityManager, NotificationService $notificationService, ReservationEmailNotifier $emailNotifier): RedirectResponse
    {
        $this->verifierAccesHote($reservation);

        if (!$this->isCsrfTokenValid('host_reservation_refuse_'.$reservation->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expiree. Reessayez.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
        }

        if ($reservation->statut !== ReservationStatut::EN_ATTENTE_HOTE) {
            $this->addFlash('error', 'Cette reservation ne peut plus etre refusee.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
        }

        $motif = trim((string) $request->request->get('motif_refus', ''));
        if ($motif === '') {
            $this->addFlash('error', 'Le motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
        }

        $reservation->statut = ReservationStatut::REFUSEE;
        $reservation->motifRefus = $motif;
        $reservation->dateAnnulation = new \DateTimeImmutable();

        $notificationService->creer(
            $reservation->voyageur,
            'reservation_refusee',
            'Demande refusee',
            sprintf('Votre demande pour %s a ete refusee par l hote. Motif : %s', $reservation->logement->titre, $motif),
            $this->generateUrl('app_reservation_show', ['id' => $reservation->id]),
        );
        $entityManager->flush();
        $emailNotifier->reservationRefusee($reservation);

        $this->addFlash('success', 'Demande refusee.');

        return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
    }

    #[Route('/{id}/annuler', name: 'app_host_reservation_cancel', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function cancel(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService,
        DisponibiliteService $disponibilites,
        ReservationEmailNotifier $emailNotifier,
    ): RedirectResponse {
        $this->verifierAccesHote($reservation);

        if (!$this->isCsrfTokenValid('host_reservation_cancel_'.$reservation->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expiree. Reessayez.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
        }

        if (!$this->peutEtreAnnulee($reservation)) {
            $this->addFlash('error', 'Cette reservation ne peut plus etre annulee.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
        }

        $motif = trim((string) $request->request->get('motif_annulation', ''));
        if ($motif === '') {
            $this->addFlash('error', 'Le motif d annulation est obligatoire.');

            return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
        }

        if ($reservation->statut === ReservationStatut::CONFIRMEE) {
            $disponibilites->libererPeriodeReservee($reservation);
        }

        $reservation->statut = ReservationStatut::ANNULEE_PAR_HOTE;
        $reservation->motifAnnulation = $motif;
        $reservation->dateAnnulation = new \DateTimeImmutable();

        $notificationService->creer(
            $reservation->voyageur,
            'reservation_annulee',
            'Reservation annulee par l hote',
            sprintf('Votre reservation pour %s a ete annulee par l hote. Motif : %s', $reservation->logement->titre, $motif),
            $this->generateUrl('app_reservation_show', ['id' => $reservation->id]),
        );

        $entityManager->flush();
        $emailNotifier->annulationHote($reservation);
        $this->addFlash('success', 'Reservation annulee.');

        return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
    }

    private function peutEtreAnnulee(Reservation $reservation): bool
    {
        return in_array($reservation->statut, [
            ReservationStatut::EN_ATTENTE_HOTE,
            ReservationStatut::ACCEPTEE_EN_ATTENTE_PAIEMENT,
            ReservationStatut::CONFIRMEE,
        ], true);
    }

    private function verifierAccesHote(Reservation $reservation): void
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        if ($reservation->hote->id !== $user->id) {
            throw $this->createAccessDeniedException();
        }
    }
}
