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
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_ical_export', methods: ['GET'])]
    public function export(Request $request, Property $property, ICalExportService $iCalExportService): Response
    {
        $storedToken = $property->getIcalToken();
        $providedToken = (string) $request->query->get('token', '');

        if ($storedToken === null || $storedToken === '' || !hash_equals($storedToken, $providedToken)) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED, ['Content-Type' => 'text/plain']);
        }

        return new Response($iCalExportService->generateIcs($property), Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="property-%s.ics"', $property->getId()),
        ]);
    }
}
