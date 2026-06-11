<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Service\IcalExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarExportController extends AbstractController
{
    /**
     * Export iCal sécurisé par token (Partie E).
     * Consommé par des agents externes (Google Calendar, Outlook) : pas de session,
     * l'accès repose uniquement sur le token unique du logement.
     */
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_ical_export', methods: ['GET'])]
    public function export(Property $property, Request $request, IcalExportService $icalExportService): Response
    {
        $token = (string) $request->query->get('token');
        $expected = $property->getIcalToken();

        if ($expected === null || !hash_equals($expected, $token)) {
            throw $this->createNotFoundException();
        }

        $response = new Response($icalExportService->export($property));
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="calendar.ics"');

        return $response;
    }
}
