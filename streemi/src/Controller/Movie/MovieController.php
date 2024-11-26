<?php

declare(strict_types=1);

namespace App\Controller\Movie;

use App\Entity\Movie;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MovieController extends AbstractController
{
    #[Route(path: '/movie/{id}', name: 'page_detail_movie')]
    public function detail(Movie $movie): Response
    {
        return $this->render('movie/detail.html.twig', [
            'movie' => $movie
        ]);
    }

    #[Route(path: '/serie', name: 'page_detail_serie')]
    public function detailSerie(): Response
    {
        return $this->render(view: 'movie/detail_serie.html.twig');
    }
}
