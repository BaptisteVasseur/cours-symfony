<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PropertyController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/property/{id}', name: 'app_property_show', methods: ['GET'])]
    public function show(Property $property, PropertyRepository $propertyRepository): Response
    {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;

        return $this->render('property/show.html.twig', [
            'property' => $property,
        ]);
    }
}
