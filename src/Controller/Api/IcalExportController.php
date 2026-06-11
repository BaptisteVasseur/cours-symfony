<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use App\Service\IcalExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IcalExportController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_ical_export', methods: ['GET'])]
    public function export(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
        IcalExportService $icalExportService,
    ): Response {
        $token = (string) $request->query->get('token', '');
        $property = $propertyRepository->findOneForDetail($property) ?? $property;

        if ($token === '' || !hash_equals((string) $property->getCalendarToken(), $token)) {
            throw $this->createAccessDeniedException('Token de calendrier invalide.');
        }

        $content = $icalExportService->generate($property);

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar-' . $property->getId() . '.ics"',
        ]);
    }
}
