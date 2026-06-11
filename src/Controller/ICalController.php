<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use App\Service\ICalExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ical', name: 'ical_')]
final class ICalController extends AbstractController
{
    public function __construct(
        private readonly ICalExportService $iCalExportService,
    ) {}

    #[Route('/property/{token}/calendar.ics', name: 'property', methods: ['GET'])]
    public function propertyCalendar(string $token, PropertyRepository $propertyRepository): Response
    {
        $property = $propertyRepository->findOneBy(['calendarToken' => $token]);
        if ($property === null) {
            throw $this->createNotFoundException('Calendrier introuvable.');
        }

        $ical = $this->iCalExportService->generateForProperty($property);

        return new Response($ical, 200, [
            'Content-Type'        => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => sprintf(
                'attachment; filename="property-%s.ics"',
                $property->getId(),
            ),
        ]);
    }

    #[Route('/host/{token}/calendar.ics', name: 'host', methods: ['GET'])]
    public function hostCalendar(string $token, UserRepository $userRepository): Response
    {
        $host = $userRepository->findOneBy(['calendarToken' => $token]);
        if ($host === null) {
            throw $this->createNotFoundException('Calendrier introuvable.');
        }

        $ical = $this->iCalExportService->generateForHost($host);

        return new Response($ical, 200, [
            'Content-Type'        => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => sprintf(
                'attachment; filename="host-%s.ics"',
                $host->getId(),
            ),
        ]);
    }
}
