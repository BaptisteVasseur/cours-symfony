<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use App\Service\ICalGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;  // ← AJOUTE CETTE LIGNE
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_ical_export', methods: ['GET'])]
    public function export(Request $request, string $id, PropertyRepository $propertyRepository, ICalGenerator $icalGenerator): Response  // ← AJOUTE Request $request
    {
        $property = $propertyRepository->find($id);
        
        if (!$property) {
            throw $this->createNotFoundException('Logement non trouvé.');
        }

        $token = $request->query->get('token');
        
        if (!$property->getIcalToken() || $property->getIcalToken() !== $token) {
            throw $this->createAccessDeniedException('Token iCal invalide.');
        }
        
        if ($property->getIcalTokenExpiresAt() && $property->getIcalTokenExpiresAt() < new \DateTimeImmutable()) {
            throw $this->createAccessDeniedException('Token iCal expiré.');
        }

        $icalContent = $icalGenerator->generate($property);

        return new Response(
            $icalContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="calendar-' . $property->getId() . '.ics"',
            ]
        );
    }
}