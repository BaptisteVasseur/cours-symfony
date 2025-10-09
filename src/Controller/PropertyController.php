<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
