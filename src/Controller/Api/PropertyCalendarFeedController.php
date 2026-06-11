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

/**
 * Flux iCal d'export d'un logement (Partie E). Endpoint anonyme — destiné à
 * Google Calendar / Outlook — strictement protégé par le token secret stocké
 * en base : aucune donnée n'est exposée sans token valide.
 */
final class PropertyCalendarFeedController extends AbstractController
{
    #[Route(
        '/api/properties/{id}/calendar.ics',
        name: 'api_property_calendar_ics',
        methods: ['GET'],
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(Property $property, Request $request, ICalExporter $exporter): Response
    {
        $storedToken = $property->getIcalExportToken();
        $providedToken = (string) $request->query->get('token', '');

        // hash_equals : comparaison en temps constant contre les attaques timing.
        // AccessDeniedHttpException -> vrai 403 (pas de redirection vers le login,
        // le flux est consommé par des agendas, pas par un navigateur connecté).
        if ($storedToken === null || $providedToken === '' || !hash_equals($storedToken, $providedToken)) {
            throw new AccessDeniedHttpException('Token de synchronisation invalide ou révoqué.');
        }

        return new Response($exporter->buildFeed($property), Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="calendar.ics"',
        ]);
    }
}
