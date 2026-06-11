<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PropertyController extends AbstractController
{
    #[Route('/properties', name: 'app_property_index')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        $properties = $propertyRepository->findBy(['status' => 'active'], ['createdAt' => 'DESC']);

        return $this->render('property/index.html.twig', [
            'properties' => $properties,
        ]);
    }

    #[Route('/properties/{id}', name: 'app_property_show')]
    public function show(string $id, PropertyRepository $propertyRepository): Response
    {
        $property = $propertyRepository->find($id);

        if (!$property) {
            throw $this->createNotFoundException('Propriété introuvable.');
        }

        return $this->render('property/show.html.twig', [
            'property' => $property,
        ]);
    }

    #[Route('/myproperties', name: 'app_property_myproperties')]
    public function myProperties(PropertyRepository $propertyRepository): Response
    {
        $user = $this->getUser();
        $properties = $propertyRepository->findBy(['host' => $user], ['createdAt' => 'DESC']);

        return $this->render('property/myproperties.html.twig', [
            'properties' => $properties,
        ]);
    }
}
