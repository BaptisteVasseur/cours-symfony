<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Service\IcalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class PropertyCalendarController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_calendar_ics', methods: ['GET'])]
    public function export(Request $request, Property $property, IcalService $icalService): Response
    {
        $token = (string) $request->query->get('token', '');
        $expectedToken = $property->getICalExportToken();

        if ($expectedToken === null || $token === '' || !hash_equals($expectedToken, $token)) {
            throw new AccessDeniedHttpException('Token iCal invalide.');
        }

        return new Response($icalService->buildCalendar($property), Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="calendar.ics"',
        ]);
    }
}
