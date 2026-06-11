<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\PropertyRepository;
use App\Service\Booking\PropertyICalExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class PropertyICalController extends AbstractController
{
    #[Route('/calendar/{token}.ics', name: 'app_property_ical_export', methods: ['GET'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function export(
        string $token,
        PropertyRepository $propertyRepository,
        PropertyICalExporter $propertyICalExporter,
    ): Response {
        $property = $propertyRepository->findOneBy(['iCalExportToken' => $token]);

        if ($property === null) {
            throw $this->createNotFoundException('Flux iCal introuvable.');
        }

        $response = new Response($propertyICalExporter->export($property));
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Cache-Control', 'no-cache, private');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                sprintf('calendar-%s.ics', $property->getId()),
            ),
        );

        return $response;
    }
}
