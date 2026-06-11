<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reservations')]
#[IsGranted('ROLE_ADMIN')]
final class ReservationCrudController extends AbstractController
{
    #[Route('', name: 'admin_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $repo): Response
    {
        return $this->render('admin/reservation/index.html.twig', [
            'reservations' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $reservation = new Reservation();
        $reservation->setCurrency('EUR');
        $reservation->setStatus('pending');
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($reservation);
            $em->flush();
            $this->addFlash('success', 'Réservation créée.');

            return $this->redirectToRoute('admin_reservation_index');
        }

        return $this->render('admin/reservation/form.html.twig', [
            'form' => $form,
            'heading' => 'Nouvelle réservation',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_reservation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Réservation mise à jour.');

            return $this->redirectToRoute('admin_reservation_index');
        }

        return $this->render('admin/reservation/form.html.twig', [
            'form' => $form,
            'heading' => 'Modifier la réservation',
        ]);
    }

    #[Route('/{id}', name: 'admin_reservation_delete', methods: ['POST'])]
    public function delete(Request $request, Reservation $reservation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reservation->getId(), $request->request->get('_token'))) {
            $em->remove($reservation);
            $em->flush();
            $this->addFlash('success', 'Réservation supprimée.');
        }

        return $this->redirectToRoute('admin_reservation_index');
    }
}
