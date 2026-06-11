<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Service\ICalExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarController extends AbstractController
{
    /**
     * Public iCal feed for a property, authenticated solely by the per-property export token.
     */
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_api_property_calendar', methods: ['GET'])]
    public function calendar(Property $property, Request $request, ICalExporter $exporter): Response
    {
        $token = (string) $request->query->get('token');
        $expected = $property->getExportToken();

        if ($expected === null || $token === '' || !hash_equals($expected, $token)) {
            throw new AccessDeniedHttpException('Jeton de synchronisation invalide.');
        }

        return new Response($exporter->export($property), Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar.ics"',
        ]);
    }
}
