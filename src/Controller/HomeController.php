<?php

namespace App\Controller;

use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @deprecated Use App\Controller\Front\HomeController instead.
 */
class HomeController extends AbstractController
{
    #[Route('/legacy', name: 'legacy_home')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        return $this->redirectToRoute('app_home');
    }
}
