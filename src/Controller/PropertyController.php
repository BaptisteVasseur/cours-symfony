<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PropertyController extends AbstractController
{
    /**
     * Page de détail d'une annonce.
     * Property auto-résolu depuis l'uuid {id} (EntityValueResolver).
     */
    #[Route('/annonce/{id}', name: 'app_property_show')]
    public function show(Property $property): Response
    {
        return $this->render('pages/property/show.html.twig', [
            'property' => $property,
        ]);
    }
}
