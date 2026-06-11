<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Service\ICalExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'api_ical_export', methods: ['GET'])]
    public function export(
        Property $property,
        Request $request,
        ICalExportService $iCalExportService,
    ): Response {
        $token = (string) $request->query->get('token', '');

        if ($token === '' || $property->getCalendarToken() === null || !hash_equals($property->getCalendarToken(), $token)) {
            return new Response('Token iCal invalide ou manquant.', Response::HTTP_FORBIDDEN);
        }

        $content = $iCalExportService->generate($property);

        return new Response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="property-%s.ics"', $property->getId()),
            'Cache-Control' => 'no-cache, no-store',
        ]);
    }
}
