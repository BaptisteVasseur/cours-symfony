<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\PropertyRepository;

final class AdminController extends AbstractController
{
   #[Route('/admin', name: 'admin_dashboard')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        $properties = $propertyRepository->findAll();
        return $this->render('admin/index.html.twig', [
            'properties' => $properties,
            'stats' => [
                'users'        => 0, // à brancher plus tard
                'properties'   => $propertyRepository->count([]),
                'reservations' => 0,
                'revenue'      => 0,
            ],
        ]);
    }
}
