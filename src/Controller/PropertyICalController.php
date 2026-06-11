<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Service\ICalExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PropertyICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_ical_export', methods: ['GET'])]
    public function export(Property $property, Request $request, ICalExporter $exporter): Response
    {
        $token = $request->query->getString('token');
        $expected = $property->getIcalExportToken();

        if ($expected === null || $token === '' || !hash_equals($expected, $token)) {
            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        $response = new Response($exporter->export($property));
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="calendar.ics"');

        return $response;
    }
}
