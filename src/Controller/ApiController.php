<?php

namespace App\Controller;

use App\Entity\Listing;
use App\Service\ICalExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class ApiController extends AbstractController
{
    #[Route('/properties/{id}/calendar.ics', name: 'api_ical_export', methods: ['GET'])]
    public function icalExport(Listing $listing, Request $request, ICalExportService $icalService): Response
    {
        $token = $request->query->get('token', '');

        if ($token === '' || $listing->getIcalToken() === null || !hash_equals($listing->getIcalToken(), $token)) {
            throw $this->createAccessDeniedException();
        }

        return new Response(
            $icalService->generate($listing),
            200,
            [
                'Content-Type'        => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="calendar.ics"',
            ]
        );
    }
}
