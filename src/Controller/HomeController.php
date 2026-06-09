<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'properties' => $propertyRepository->findMostPopular(),
        ]);
    }

    #[Route('/logement/{id}', name: 'app_logement_detail')]
    public function detail(Property $property): Response
    {
        return $this->render('home/logement.html.twig', [
            'mon_logement' => $property
        ]);
    }

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, PropertyRepository $propertyRepository): Response
    {

    }

    #[Route('/register', name: 'app_register')]
    public function register(): Response
    {
        return $this->render('home/register.html.twig');
    }
}
