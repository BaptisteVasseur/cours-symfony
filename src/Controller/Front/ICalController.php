<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_ical_export', methods: ['GET'])]
    public function export(
        Request $request,
        Property $property,
        BookingService $bookingService
    ): Response {
        $token = $request->query->get('token');

        if ($property->getCalendarToken() === null || $property->getCalendarToken() !== $token) {
            return new Response('Accès refusé. Token invalide ou non configuré.', Response::HTTP_FORBIDDEN);
        }

        $icalContent = $bookingService->generateIcal($property);

        return new Response(
            $icalContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="calendar.ics"',
            ]
        );
    }
}
