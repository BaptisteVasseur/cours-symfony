<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Service\ICalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/properties')]
final class ICalController extends AbstractController
{
    #[Route('/{id}/calendar.ics', name: 'app_property_ical', methods: ['GET'])]
    public function calendarIcs(
        Request $request,
        Property $property,
        ICalService $icalService,
    ): Response {
        $token = $request->query->get('token');

        if ($token === null || $token === '') {
            return new Response('Token manquant.', Response::HTTP_UNAUTHORIZED);
        }

        if ($property->getIcalToken() === null || !hash_equals($property->getIcalToken(), $token)) {
            return new Response('Token invalide.', Response::HTTP_FORBIDDEN);
        }

        $icalContent = $icalService->generateFeed($property);

        return new Response(
            $icalContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'inline; filename="calendar.ics"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ],
        );
    }
}
