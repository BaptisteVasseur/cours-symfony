<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PropertyRepository;
use App\Service\ICalExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ICalController extends AbstractController
{
    public function __construct(
        private readonly PropertyRepository $propertyRepo,
        private readonly ICalExportService $exportService,
    ) {
    }

    #[Route('/property/{icalToken}/calendar.ics', name: 'property_ical_export', methods: ['GET'])]
    public function export(string $icalToken): Response
    {
        $property = $this->propertyRepo->findOneBy(['icalToken' => $icalToken]);

        if ($property === null) {
            throw $this->createNotFoundException();
        }

        $content = $this->exportService->generateCalendar($property);

        return new Response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar.ics"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
