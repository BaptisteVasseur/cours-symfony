<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    #[Route('/search', name: 'search_property')]
    public function index(Request $request, PropertyRepository $propertyRepository): Response
    {
        $city = $request->query->get('city');
        $startAt = $request->query->get('startAt');
        $endAt = $request->query->get('endAt');
        $travelers = $request->query->get('travelers');
        $page = $request->query->get('page');
        $numberOfElementsPerPage = $request->query->get('numberOfElementsPerPage');

        $properties = $propertyRepository->search($city, $startAt, $endAt, $travelers, $page, $numberOfElementsPerPage);

        return $this->render('home/search.html.twig', [
            'properties' => $properties,
            'searchParams' => [
                'city' => $city,
                'startAt' => $startAt,
                'endAt' => $endAt,
                'travelers' => $travelers,
            ],
        ]);
    }
}
