<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @deprecated Use App\Controller\Admin\PropertyController instead.
 */
class PropertyController extends AbstractController
{
    #[Route('/legacy/properties', name: 'legacy_property_index')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_property_index');
    }

    #[Route('/legacy/properties/{id}', name: 'legacy_property_show')]
    public function show(string $id): Response
    {
        return $this->redirectToRoute('app_property_show', ['id' => $id]);
    }
}
