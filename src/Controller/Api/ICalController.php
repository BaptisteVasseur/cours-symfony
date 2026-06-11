<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use App\Service\ICalExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Secured iCal export endpoint — public access controlled by token.
 */
final class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_api_ical_export', methods: ['GET'])]
    public function export(
        Property $property,
        Request $request,
        ReservationRepository $reservationRepository,
        ICalExportService $icalExportService,
    ): Response {
        $token = $request->query->get('token');

        if ($token === null || $property->getCalendarToken() === null || $property->getCalendarToken() !== $token) {
            throw $this->createAccessDeniedException('Token invalide ou manquant.');
        }

        $reservations = $reservationRepository->findConfirmedByProperty($property);
        $content = $icalExportService->generate($property, $reservations);

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar.ics"',
        ]);
    }
}
