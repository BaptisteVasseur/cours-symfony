<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Service\ICalExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PropertyCalendarController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_api_property_calendar', methods: ['GET'])]
    public function calendar(Request $request, Property $property, ICalExporter $iCalExporter): Response
    {
        $token = (string) $request->query->get('token');
        $expected = $property->getCalendarToken();

        if ($expected === null || $token === '' || !hash_equals($expected, $token)) {
            return new Response('Jeton de synchronisation invalide.', Response::HTTP_FORBIDDEN);
        }

        return new Response(
            $iCalExporter->export($property),
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => sprintf('attachment; filename="property-%s.ics"', $property->getId()),
            ],
        );
    }
}
