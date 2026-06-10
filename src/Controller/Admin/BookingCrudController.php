<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Form\BookingFormType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/bookings')]
final class BookingCrudController extends AbstractController
{
    #[Route('', name: 'admin_booking_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        return $this->render('admin/booking/index.html.twig', [
            'bookings' => $reservationRepository->findAllForListing(),
        ]);
    }

    #[Route('/new', name: 'admin_booking_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $booking = new Reservation();
        $booking
            ->setStatus('pending')
            ->setGuestsCount(1)
            ->setCurrency('EUR');

        $form = $this->createForm(BookingFormType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($booking);
            $entityManager->flush();
            $this->addFlash('success', 'Réservation créée.');

            return $this->redirectToRoute('admin_booking_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/booking/form.html.twig', [
            'form' => $form,
            'booking' => $booking,
            'title' => 'Nouvelle réservation',
            'button_label' => 'Créer',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_booking_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reservation $booking, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BookingFormType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Réservation mise à jour.');

            return $this->redirectToRoute('admin_booking_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/booking/form.html.twig', [
            'form' => $form,
            'booking' => $booking,
            'title' => 'Modifier la réservation',
            'button_label' => 'Mettre à jour',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_booking_delete', methods: ['POST'])]
    public function delete(Request $request, Reservation $booking, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $booking->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $entityManager->remove($booking);
        $entityManager->flush();
        $this->addFlash('success', 'Réservation supprimée.');

        return $this->redirectToRoute('admin_booking_index', [], Response::HTTP_SEE_OTHER);
    }
}
