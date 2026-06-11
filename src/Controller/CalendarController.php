<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Service\ICalExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CalendarController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_calendar_ics', methods: ['GET'])]
    public function ics(Property $property, Request $request, ICalExporter $exporter): Response
    {
        $token = (string) $request->query->get('token', '');
        $expected = (string) $property->getCalendarToken();

        if ($expected === '' || !hash_equals($expected, $token)) {
            return new Response('Token de calendrier invalide.', Response::HTTP_FORBIDDEN);
        }

        $response = new Response($exporter->export($property));
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'inline; filename="calendar.ics"');

        return $response;
    }
}