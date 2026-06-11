<?php

namespace App\Controller;

use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        $properties = $propertyRepository->findAll();
        return $this->render('home/index.html.twig', [
            'properties' => $properties,
        ]);
    }

    #[Route('/form', name: 'form_home')]
    public function form(): Response
    {
        return $this->render('home/form.html.twig');
    }
}