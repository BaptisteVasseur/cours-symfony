<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Service\ICalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/properties')]
final class ICalController extends AbstractController
{
    #[Route('/{id}/calendar.ics', name: 'app_api_ical_export', methods: ['GET'])]
    public function export(
        Property $property,
        Request $request,
        ICalService $icalService,
    ): Response {
        $tokenParam = $request->query->get('token');

        if ($tokenParam === null || $tokenParam === '') {
            throw $this->createAccessDeniedException('Token manquant.');
        }

        try {
            $token = Uuid::fromString($tokenParam);
        } catch (\InvalidArgumentException) {
            throw $this->createAccessDeniedException('Token invalide.');
        }

        $propertyToken = $property->getIcalToken();
        if ($propertyToken === null || !$propertyToken->equals($token)) {
            throw $this->createAccessDeniedException('Token incorrect.');
        }

        $icsContent = $icalService->generateIcs($property);
        $filename = sprintf('property-%s.ics', $property->getId());

        return new Response(
            $icsContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ],
        );
    }
}
