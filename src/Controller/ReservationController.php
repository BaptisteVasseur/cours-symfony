<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reservations')]
final class ReservationController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'app_reservation_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/reservations.html.twig');
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        return $this->render('home/reservation.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}
