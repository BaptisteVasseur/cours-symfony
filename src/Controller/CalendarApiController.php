<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Listing;
use App\Service\ICalExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarApiController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_api_calendar', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function export(Listing $listing, Request $request, ICalExportService $exportService): Response
    {
        $token = (string) $request->query->get('token', '');

        // 403 HTTP « pure » (et non AccessDeniedException de sécurité) afin de NE
        // PAS rediriger vers /login : l'endpoint est public, seul le token fait foi.
        if ($token === '' || !hash_equals($listing->getCalendarToken(), $token)) {
            throw new AccessDeniedHttpException('Jeton de calendrier invalide.');
        }

        $response = new Response($exportService->export($listing));
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="listing-%s.ics"', $listing->getId()));

        return $response;
    }
}
