<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use App\Service\ICalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


final class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'api_ical_export', methods: ['GET'])]
    public function export(
        Property $property,
        Request $request,
        ICalService $iCalService,
    ): Response {
        $token = $request->query->get('token');
        if ($token === null || $token !== $property->getIcalToken()) {
            throw $this->createAccessDeniedException('Token iCal invalide.');
        }

        $content = $iCalService->export($property);

        return new Response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar-'.$property->getId().'.ics"',
        ]);
    }
}
