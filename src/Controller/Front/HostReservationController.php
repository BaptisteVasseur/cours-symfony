<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Form\CancellationType;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/hote/reservations')]
#[IsGranted('ROLE_HOST')]
final class HostReservationController extends AbstractController
{
    #[Route('/demandes', name: 'app_host_reservation_pending', methods: ['GET'])]
    public function pending(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $rows = [];
        foreach ($reservationRepository->findPendingForHost($user) as $reservation) {
            $id = $reservation->getId()?->toRfc4122();
            $rows[] = [
                'reservation' => $reservation,
                'declineForm' => $this->createNamed('decline_' . $id, CancellationType::class, null, [
                    'action' => $this->generateUrl('app_host_reservation_decline', ['id' => $id]),
                ])->createView(),
            ];
        }

        return $this->render('front/host/reservations.html.twig', [
            'rows' => $rows,
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(Request $request, Reservation $reservation, ReservationService $reservationService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $tokenId = 'host_accept_' . $reservation->getId()?->toRfc4122();
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $reservationService->accept($reservation, $user);
            $this->addFlash('success', 'Demande acceptee.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_reservation_pending');
    }

    #[Route('/{id}/refuser', name: 'app_host_reservation_decline', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function decline(Request $request, Reservation $reservation, ReservationService $reservationService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createNamed('decline_' . $reservation->getId()?->toRfc4122(), CancellationType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Le motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_reservation_pending');
        }

        try {
            $reservationService->decline($reservation, $user, (string) $form->get('reason')->getData());
            $this->addFlash('success', 'Demande refusee.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_reservation_pending');
    }
}
