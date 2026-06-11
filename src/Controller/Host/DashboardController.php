<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\User;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\ReservationVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

#[Route('/dashboard')]
#[IsGranted('ROLE_HOST')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'app_host_dashboard', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reservations = $reservationRepository->findPendingByHost($user);

        return $this->render('host/dashboard.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/reservation/{id}/accept', name: 'app_host_reservation_accept', methods: ['POST'])]
    public function accept(Reservation $reservation, Request $request, EntityManagerInterface $entityManager, \Symfony\Component\Mailer\MailerInterface $mailer, \Twig\Environment $twig): Response
    {
        $this->denyAccessUnlessGranted(ReservationVoter::MANAGE, $reservation);

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('accept-reservation'.$reservation->getId(), $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_host_dashboard');
        }

        $reservation->setStatus('confirmed');
        $entityManager->persist($reservation);
        $entityManager->flush();

        // send notification (confirmed) to guest and host
        $body = $twig->render('parts/reservation_confirmed_email.html.twig', ['reservation' => $reservation]);
        $guestEmail = $reservation->getGuest()?->getEmail();
        $hostEmail = $reservation->getProperty()?->getHost()?->getEmail();
        if ($guestEmail) {
            try {
                $mailer->send((new \Symfony\Component\Mime\Email())
                    ->to($guestEmail)
                    ->subject('Réservation confirmée')
                    ->html($body));
            } catch (\Throwable $e) { }
        }
        if ($hostEmail) {
            try {
                $mailer->send((new \Symfony\Component\Mime\Email())
                    ->to($hostEmail)
                    ->subject('Réservation confirmée')
                    ->html($body));
            } catch (\Throwable $e) { }
        }

        $this->addFlash('success', 'Réservation acceptée.');

        return $this->redirectToRoute('app_host_dashboard');
    }

    #[Route('/reservation/{id}/reject', name: 'app_host_reservation_reject', methods: ['POST'])]
    public function reject(Reservation $reservation, Request $request, EntityManagerInterface $entityManager, \Symfony\Component\Mailer\MailerInterface $mailer, \Twig\Environment $twig): Response
    {
        $this->denyAccessUnlessGranted(ReservationVoter::MANAGE, $reservation);

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('reject-reservation'.$reservation->getId(), $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_host_dashboard');
        }

        $reason = trim((string) $request->request->get('reason', ''));

        if ($reason === '') {
            $this->addFlash('error', 'Le motif est obligatoire pour refuser une réservation.');

            return $this->redirectToRoute('app_host_dashboard');
        }

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);

        $entityManager->persist($reservation);
        $entityManager->flush();

        // send notification (cancelled) with reason
        $body = $twig->render('parts/reservation_cancelled_email.html.twig', ['reservation' => $reservation, 'reason' => $reason]);
        $guestEmail = $reservation->getGuest()?->getEmail();
        $hostEmail = $reservation->getProperty()?->getHost()?->getEmail();
        if ($guestEmail) {
            try {
                $mailer->send((new \Symfony\Component\Mime\Email())
                    ->to($guestEmail)
                    ->subject('Réservation annulée / refusée')
                    ->html($body));
            } catch (\Throwable $e) { }
        }
        if ($hostEmail) {
            try {
                $mailer->send((new \Symfony\Component\Mime\Email())
                    ->to($hostEmail)
                    ->subject('Réservation annulée / refusée')
                    ->html($body));
            } catch (\Throwable $e) { }
        }

        $this->addFlash('success', 'Réservation refusée.');

        return $this->redirectToRoute('app_host_dashboard');
    }
}
