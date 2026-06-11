<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Service\ICalExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class PropertyCalendarController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'api_property_calendar', methods: ['GET'])]
    public function export(Property $property, Request $request, ICalExportService $exporter): Response
    {
        $token = $property->getCalendarToken();
        if ($token === null || !hash_equals($token, (string) $request->query->get('token'))) {
            throw new AccessDeniedHttpException('Token de calendrier invalide.');
        }

        return new Response(
            $exporter->export($property),
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => sprintf('attachment; filename="property-%s.ics"', $property->getId()),
            ],
        );
    }
}
