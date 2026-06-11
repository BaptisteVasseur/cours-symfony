<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use App\Service\IcalCalendarBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Export iCal sécurisé d'un logement (Partie E).
 *
 * URL : /api/properties/{id}/calendar.ics?token={secret}
 * L'accès est UNIQUEMENT autorisé via le jeton secret du logement (aucune session requise,
 * mais aucune donnée exposée sans jeton valide).
 */
final class PropertyIcalController extends AbstractController
{
    #[Route(
        '/api/properties/{id}/calendar.ics',
        name: 'app_property_ical_export',
        methods: ['GET'],
    )]
    public function export(
        Request $request,
        Property $property,
        ReservationRepository $reservationRepository,
        IcalCalendarBuilder $icalBuilder,
    ): Response {
        $token = (string) $request->query->get('token', '');
        $expected = $property->getIcalToken();

        // Interdiction stricte d'exposer des données sans jeton valide.
        if ($expected === null || $token === '' || !hash_equals($expected, $token)) {
            throw new AccessDeniedHttpException('Jeton de synchronisation invalide ou manquant.');
        }

        $ics = $icalBuilder->build(
            $property,
            $reservationRepository->findConfirmedForProperty($property),
        );

        $response = new Response($ics);
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="calendar.ics"');

        return $response;
    }
}
