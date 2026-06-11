<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\ReservationStatut;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
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
    public function accept(Reservation $reservation, Request $request, EntityManagerInterface $entityManager, NotificationService $notificationService): RedirectResponse
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

        $this->addFlash('success', 'Demande acceptee. Le voyageur doit maintenant payer pour confirmer la reservation.');

        return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_refuse', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function refuse(Reservation $reservation, Request $request, EntityManagerInterface $entityManager, NotificationService $notificationService): RedirectResponse
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

        $reservation->statut = ReservationStatut::REFUSEE;
        $reservation->dateAnnulation = new \DateTimeImmutable();

        $notificationService->creer(
            $reservation->voyageur,
            'reservation_refusee',
            'Demande refusee',
            sprintf('Votre demande pour %s a ete refusee par l hote.', $reservation->logement->titre),
            $this->generateUrl('app_reservation_show', ['id' => $reservation->id]),
        );
        $entityManager->flush();

        $this->addFlash('success', 'Demande refusee.');

        return $this->redirectToRoute('app_host_reservation_show', ['id' => $reservation->id]);
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
