<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host/bookings')]
#[IsGranted('ROLE_HOST')]
class HostReservationController extends AbstractController
{
    #[Route('', name: 'app_host_booking_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        $reservations = $reservationRepository->findByHost($user);

        $revenue = 0.0;
        foreach ($reservations as $reservation) {
            if (in_array($reservation->getStatus(), ['confirmed', 'completed'], true)) {
                $revenue += (float) $reservation->getTotalPrice();
            }
        }

        return $this->render('host/booking/index.html.twig', [
            'reservations' => $reservations,
            'total' => count($reservations),
            'confirmed' => count(array_filter($reservations, static fn (Reservation $r): bool => $r->getStatus() === 'confirmed')),
            'pending' => count(array_filter($reservations, static fn (Reservation $r): bool => $r->getStatus() === 'pending')),
            'cancelled' => count(array_filter($reservations, static fn (Reservation $r): bool => $r->getStatus() === 'cancelled')),
            'revenue' => $revenue,
        ]);
    }

    #[Route('/{id}/approve', name: 'app_host_booking_approve', methods: ['POST'])]
    public function approve(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $property = $reservation->getProperty();
        if ($property === null || $property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à gérer cette réservation.');
        }

        if ($this->isCsrfTokenValid('approve' . $reservation->getId(), $request->getPayload()->getString('_token'))) {
            $reservation->setStatus('confirmed');
            $entityManager->flush();
            $this->addFlash('success', 'Réservation approuvée.');
        }

        return $this->redirectToRoute('app_host_booking_index');
    }

    #[Route('/{id}/reject', name: 'app_host_booking_reject', methods: ['POST'])]
    public function reject(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $property = $reservation->getProperty();
        if ($property === null || $property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à gérer cette réservation.');
        }

        if ($this->isCsrfTokenValid('reject' . $reservation->getId(), $request->getPayload()->getString('_token'))) {
            $reason = trim($request->getPayload()->getString('cancellationReason', 'Refusée par l\'hôte'));
            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason($reason !== '' ? $reason : 'Refusée par l\'hôte');
            $entityManager->flush();
            $this->addFlash('success', 'Réservation rejetée.');
        }

        return $this->redirectToRoute('app_host_booking_index');
    }
}
