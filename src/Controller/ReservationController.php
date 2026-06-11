<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @deprecated Use App\Controller\Front\ReservationController instead.
 */
class ReservationController extends AbstractController
{
    #[Route('/legacy/reservations', name: 'legacy_reservation_index')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_reservation_index');
    }
}
