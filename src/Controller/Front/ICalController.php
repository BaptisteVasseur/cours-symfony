<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Service\ICal\ICalExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ICalController extends AbstractController
{
    #[Route('/calendrier/{id}/export.ics', name: 'app_ical_export', methods: ['GET'])]
    public function export(
        Request $request,
        Property $property,
        ICalExporter $exporter,
    ): Response {
        $token = (string) $request->query->get('token', '');
        $expected = $property->getIcalToken();

        if ($expected === null || $token === '' || !hash_equals($expected, $token)) {
            throw new AccessDeniedHttpException('Jeton d\'accès au calendrier invalide.');
        }

        $response = new Response($exporter->export($property));
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="calendrier.ics"');

        return $response;
    }
}
