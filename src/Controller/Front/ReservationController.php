<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\BookingService;
use App\Service\ReservationWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    /**
     * Tableau de bord hôte : demandes en attente sur ses logements (B.2).
     * Déclarée avant /{id} pour ne pas être capturée par le paramètre.
     */
    #[Route('/demandes', name: 'app_reservation_requests', methods: ['GET'])]
    public function requests(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/reservation/requests.html.twig', [
            'reservations' => $reservationRepository->findPendingForHost($user),
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function show(
        Reservation $reservation,
        ReservationRepository $reservationRepository,
        BookingService $bookingService,
        ReservationWorkflow $workflow,
    ): Response {
        if ($workflow->expireStalePaymentLock($reservation)) {
            $this->addFlash('error', 'Le délai de paiement de 15 minutes est dépassé : la réservation a été annulée et les dates libérées.');
        }

        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;
        $user = $this->getUser();

        return $this->render('front/reservation/show.html.twig', [
            'reservation' => $reservation,
            'paymentDeadline' => $bookingService->paymentDeadline($reservation),
            'isHost' => $reservation->getProperty()?->getHost()?->getId()?->equals($user?->getId()) === true,
        ]);
    }

    /**
     * Validation par le paiement : confirme la demande du voyageur (B.1).
     */
    #[Route('/{id}/payer', name: 'app_reservation_pay', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function pay(Request $request, Reservation $reservation, ReservationWorkflow $workflow): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $reservation->getGuest()?->getId()?->equals($user->getId()) !== true) {
            throw $this->createAccessDeniedException('Seul le voyageur peut payer cette réservation.');
        }
        if (!$this->isCsrfTokenValid('pay-' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $result = $workflow->confirm($reservation, $user);
        $this->addFlash(...$this->confirmFlash($result));

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
    }

    /**
     * Acceptation d'une demande par l'hôte (B.2).
     */
    #[Route('/{id}/accepter', name: 'app_reservation_accept', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(Request $request, Reservation $reservation, ReservationWorkflow $workflow): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if (!$this->isCsrfTokenValid('accept-' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $result = $workflow->confirm($reservation, $user);
        $this->addFlash(...$this->confirmFlash($result));

        return $this->redirectToRoute('app_reservation_requests');
    }

    /**
     * Refus d'une demande par l'hôte, avec motif obligatoire (B.2).
     */
    #[Route('/{id}/refuser', name: 'app_reservation_refuse', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function refuse(Request $request, Reservation $reservation, ReservationWorkflow $workflow): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if (!$this->isCsrfTokenValid('refuse-' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $reason = trim((string) $request->request->get('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif du refus est obligatoire.');

            return $this->redirectToRoute('app_reservation_requests');
        }

        $result = $workflow->refuse($reservation, $user, $reason);
        $this->addFlash(
            $result === ReservationWorkflow::RESULT_OK ? 'success' : 'error',
            $result === ReservationWorkflow::RESULT_OK
                ? 'La demande a été refusée et le voyageur a été notifié.'
                : 'Cette demande ne peut plus être refusée.',
        );

        return $this->redirectToRoute('app_reservation_requests');
    }

    /**
     * Annulation d'une réservation par l'hôte ou le voyageur, avec motif (B.3).
     */
    #[Route('/{id}/annuler', name: 'app_reservation_cancel', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function cancel(Request $request, Reservation $reservation, ReservationWorkflow $workflow): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if (!$this->isCsrfTokenValid('cancel-' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $reason = trim((string) $request->request->get('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif de l\'annulation est obligatoire.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $result = $workflow->cancel($reservation, $user, $reason);
        $this->addFlash(
            $result === ReservationWorkflow::RESULT_OK ? 'success' : 'error',
            $result === ReservationWorkflow::RESULT_OK
                ? 'La réservation a été annulée, les dates sont libérées et les deux parties notifiées.'
                : 'Cette réservation ne peut pas être annulée.',
        );

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function confirmFlash(string $result): array
    {
        return match ($result) {
            ReservationWorkflow::RESULT_OK => ['success', 'Réservation confirmée : les dates sont verrouillées et les parties notifiées.'],
            ReservationWorkflow::RESULT_UNAVAILABLE => ['error', 'Impossible de confirmer : une autre réservation occupe désormais ces dates.'],
            default => ['error', 'Cette réservation ne peut plus être confirmée.'],
        };
    }
}
