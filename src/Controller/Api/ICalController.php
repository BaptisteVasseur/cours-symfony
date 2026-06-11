<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use App\Service\ICalExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'api_property_ical', methods: ['GET'])]
    public function export(
        Property $property,
        Request $request,
        ICalExportService $icalService,
    ): Response {
        $token = $request->query->get('token', '');

        if ($token === '' || $token !== $property->getCalendarToken()) {
            throw $this->createAccessDeniedException('Token invalide.');
        }

        $ical = $icalService->generate($property);

        return new Response($ical, 200, [
            'Content-Type'        => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="calendar.ics"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
