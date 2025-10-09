<?php

namespace App\Controller;

use App\Entity\Property;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\PropertyRepository;

final class HomeController extends AbstractController
{

    #[Route('/home', name: 'app_home')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        $properties = $propertyRepository->findPopulars();

        return $this->render('home/index.html.twig', [
            'properties' => $properties,
        ]);
    }

    #[Route('/search', name: 'app_home_search')]
    public function search(PropertyRepository $propertyRepository, Request $request): Response
    {
        $properties = $propertyRepository->search($request);

        return $this->render('home/search.html.twig', [
            'properties' => $properties,
        ]);
    }
}
