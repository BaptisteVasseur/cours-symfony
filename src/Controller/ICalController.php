<?php

namespace App\Controller;

use App\Entity\Property;
use App\Service\ICalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_ical_export')]
    public function export(Property $property, Request $request, ICalService $icalService): Response
    {
        $token = $request->query->get('token');

        if (!$token || !$property->getCalendarToken() || !hash_equals($property->getCalendarToken(), $token)) {
            throw $this->createAccessDeniedException('Token iCal invalide ou manquant.');
        }

        $icsContent = $icalService->generateCalendar($property);

        return new Response($icsContent, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => sprintf(
                'attachment; filename="calendar-%s.ics"',
                $property->getId()
            ),
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
