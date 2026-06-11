<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use App\Service\ICalExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarExportController extends AbstractController
{
    /**
     * Flux iCal public des séjours confirmés, sécurisé par un token révocable
     * propre au logement : /api/properties/{id}/calendar.ics?token={secret}
     */
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_ical', methods: ['GET'])]
    public function calendar(
        Property $property,
        Request $request,
        ReservationRepository $reservationRepository,
        ICalExporter $exporter,
    ): Response {
        $expected = $property->getIcalToken();
        $provided = $request->query->get('token');

        if ($expected === null || !is_string($provided) || !hash_equals($expected, $provided)) {
            throw $this->createNotFoundException();
        }

        $ics = $exporter->export($property, $reservationRepository->findConfirmedForProperty($property));

        return new Response($ics, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="calendar.ics"',
        ]);
    }
}
