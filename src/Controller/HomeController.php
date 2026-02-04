<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/property/{id}', name: 'app_property_detail')]
    public function detail(int $id): Response
    {
        return $this->render('home/detail.html.twig', [
            'id' => $id,
        ]);
    }
}
