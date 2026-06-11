<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use App\Service\ICal\ICalExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Expose le flux iCal d'export d'un logement (énoncé §Partie E).
 *
 * Endpoint public mais protégé par un jeton secret propre au logement, stocké
 * en base et révocable par l'hôte. La comparaison se fait en temps constant
 * (hash_equals). Aucune donnée n'est exposée sans jeton valide.
 */
final class PropertyCalendarController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_calendar_ics', methods: ['GET'])]
    public function calendar(
        Property $property,
        Request $request,
        ICalExporter $exporter,
        ReservationRepository $reservationRepository,
    ): Response {
        $expected = (string) $property->getIcalExportToken();
        $provided = (string) $request->query->get('token', '');

        if ($expected === '' || !hash_equals($expected, $provided)) {
            // 403 « dur » (et non redirection vers le login) : c'est un endpoint
            // d'API authentifié par jeton, destiné à des clients calendrier.
            throw new AccessDeniedHttpException('Jeton de calendrier invalide ou révoqué.');
        }

        $ics = $exporter->export($property, $reservationRepository->findConfirmedForProperty($property));

        $response = new Response($ics);
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="calendar.ics"');

        return $response;
    }
}
