<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Service\Calendar\IcalExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class PropertyCalendarController extends AbstractController
{
    #[Route(
        '/api/properties/{id}/calendar.ics',
        name: 'app_api_property_calendar',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function __invoke(
        string $id,
        Request $request,
        PropertyRepository $propertyRepository,
        ReservationRepository $reservationRepository,
        IcalExporter $icalExporter,
    ): Response {
        $property = $propertyRepository->find($id);
        $token = $request->query->getString('token');
        $storedToken = $property?->getIcalExportToken();

        if ($property === null || $storedToken === null || $token === '' || !hash_equals($storedToken, $token)) {
            throw $this->createNotFoundException('Calendrier introuvable.');
        }

        $ics = $icalExporter->build($property, $reservationRepository->findConfirmedForProperty($property));

        return new Response($ics, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, 'calendar.ics'),
        ]);
    }
}
