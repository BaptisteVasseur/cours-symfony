<?php

namespace App\Controller;

use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, PropertyRepository $propertyRepository): Response
    {
        $city = $request->query->get('city');
        $guests = $request->query->getInt('guests') ?: null;

        $properties = $propertyRepository->findPublished($city, $guests);

        return $this->render('home/index.html.twig', [
            'properties' => $properties,
            'city' => $city,
            'guests' => $guests,
        ]);
    }
}
