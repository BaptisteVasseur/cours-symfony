<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use App\Service\ICal\ICalCalendarBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PropertyICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_calendar_export', methods: ['GET'])]
    public function export(
        Request $request,
        Property $property,
        ReservationRepository $reservationRepository,
        ICalCalendarBuilder $calendarBuilder,
    ): Response {
        $expectedToken = $property->getICalExportToken();
        $providedToken = $request->query->getString('token');

        if ($expectedToken === null || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            throw $this->createNotFoundException();
        }

        $content = $calendarBuilder->build($property, $reservationRepository->findConfirmedForProperty($property));

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="property-'.$property->getId().'.ics"',
        ]);
    }
}
