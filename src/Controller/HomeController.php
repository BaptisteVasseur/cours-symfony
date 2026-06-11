<?php

namespace App\Controller;

use App\Repository\LogementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(LogementRepository $logements): Response
    {
        return $this->render('home/index.html.twig', [
            'logements_populaires' => $logements->trouverPopulaires(),
        ]);
    }
}
