<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Form\PropertyType;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PropertyController extends AbstractController
{
    #[Route('/property/{id}', name: 'app_property_detail', requirements: ['id' => '\d+'])]
    public function propertyDetail(Property $property): Response
    {
        return $this->render('home/property-detail.html.twig', [
            'property' => $property,
        ]);
    }

    #[Route('/property/add', name: 'app_property_create')]
    public function propertyCreate(Request $request, EntityManagerInterface $entityManager): Response
    {
        $property = new Property();
        $form = $this->createForm(PropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($property);
            $entityManager->flush();
        }

        return $this->render('property/add.html.twig', [
            'form' => $form,
        ]);
    }
}
