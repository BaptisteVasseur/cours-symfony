<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AirbnbController extends AbstractController
{
    #[Route('/airbnb', name: 'airbnb_index')]
    public function index(): Response
    {
        $listings = [
            ['title' => 'Beachfront Villa, Malibu', 'price' => 320, 'image' => 'https://...'],
            ['title' => 'Alpine Chalet, Chamonix', 'price' => 185, 'image' => 'https://...'],
        ];

        return $this->render('airbnb/index.html.twig', [
            'listings' => $listings,
        ]);
    }

    #[Route('/airbnb/admin', name: 'airbnb_admin')]
    public function admin(): Response
    {
        return $this->render('airbnb/admin.html.twig');
    }
}
