<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Dispute;
use App\Entity\Reservation;
use App\Form\AdminRejectType;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reservation')]
#[IsGranted('ROLE_ADMIN')]
final class AdminReservationController extends AbstractController
{
    #[Route(name: 'app_admin_reservation_index', methods: ['GET'])]
    public function index(Request $request, ReservationRepository $reservationRepository): Response
    {
        $search = $request->query->getString('q');
        $search = $search !== '' ? $search : null;
        $statusFilter = $request->query->getString('status');
        $statusFilter = $statusFilter !== '' ? $statusFilter : null;
        $sort = $request->query->getString('sort', 'createdAt');
        $dir  = $request->query->getString('dir', 'DESC');

        $reservations = $reservationRepository->findAllForListing($search, $statusFilter, $sort, $dir);

        $allReservations = $reservationRepository->findAllForListing();
        $revenue = 0.0;
        foreach ($allReservations as $reservation) {
            if (in_array($reservation->getStatus(), ['confirmed', 'completed'], true)) {
                $revenue += (float) $reservation->getTotalPrice();
            }
        }

        return $this->render('admin_reservation/index.html.twig', [
            'reservations'  => $reservations,
            'total'         => $reservationRepository->count([]),
            'confirmed'     => $reservationRepository->count(['status' => 'confirmed']),
            'pending'       => $reservationRepository->count(['status' => 'pending']),
            'cancelled'     => $reservationRepository->count(['status' => 'cancelled']),
            'revenue'       => $revenue,
            'search'        => $search ?? '',
            'statusFilter'  => $statusFilter,
            'sort'          => $sort,
            'dir'           => $dir,
        ]);
    }

    #[Route('/new', name: 'app_admin_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reservation = new Reservation();
        if ($reservation->getCurrency() === null) {
            $reservation->setCurrency('EUR');
        }

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($reservation);
            $entityManager->flush();

            $this->addFlash('success', 'Réservation créée avec succès.');

            return $this->redirectToRoute('app_admin_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin_reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;
        $rejectForm  = $this->createForm(AdminRejectType::class, null, [
            'action' => $this->generateUrl('app_admin_reservation_reject', ['id' => $reservation->getId()]),
            'method' => 'POST',
        ]);

        return $this->render('admin_reservation/show.html.twig', [
            'reservation' => $reservation,
            'rejectForm'  => $rejectForm,
        ]);
    }

    #[Route('/{id}/approve', name: 'app_admin_reservation_approve', methods: ['POST'])]
    public function approve(Request $request, Reservation $reservation, BookingService $bookingService): Response
    {
        if (!$this->isCsrfTokenValid('admin_approve_' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_reservation_show', ['id' => $reservation->getId()]);
        }

        try {
            $bookingService->confirm($reservation);
            $this->addFlash('success', 'Réservation confirmée.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/reject', name: 'app_admin_reservation_reject', methods: ['POST'])]
    public function reject(Request $request, Reservation $reservation, BookingService $bookingService): Response
    {
        $form = $this->createForm(AdminRejectType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $preset = $form->get('presetReason')->getData();
            $custom = trim((string) $form->get('customReason')->getData());

            $presetLabels = array_flip(AdminRejectType::PRESET_REASONS);
            $presetLabel  = $presetLabels[$preset] ?? $preset;

            $reason = $preset === 'other'
                ? ($custom !== '' ? $custom : 'Autre motif')
                : ($custom !== '' ? $presetLabel . ' — ' . $custom : $presetLabel);

            try {
                $bookingService->rejectByAdmin($reservation, $reason);
                $this->addFlash('success', 'Réservation refusée.');
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Veuillez sélectionner un motif de refus.');
        }

        return $this->redirectToRoute('app_admin_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_admin_reservation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Réservation mise à jour avec succès.');

            return $this->redirectToRoute('app_admin_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin_reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_reservation_delete', methods: ['POST'])]
    public function delete(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($reservation);
            $entityManager->flush();

            $this->addFlash('success', 'Réservation supprimée.');
        }

        return $this->redirectToRoute('app_admin_reservation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/dispute/{id}/resolve', name: 'app_admin_dispute_resolve', methods: ['POST'])]
    public function resolveDispute(Request $request, Dispute $dispute, EntityManagerInterface $entityManager): Response
    {
        $reservation = $dispute->getReservation();
        if ($reservation === null || !$this->isCsrfTokenValid('dispute'.$dispute->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_admin_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        $resolution = trim($request->getPayload()->getString('resolution'));
        if ($resolution !== '') {
            $dispute->setResolution($resolution);
            $dispute->setStatus('resolved');
            $entityManager->flush();
            $this->addFlash('success', 'Litige résolu.');
        }

        return $this->redirectToRoute('app_admin_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
    }
}
