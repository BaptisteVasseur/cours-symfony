<?php

declare(strict_types=1);

namespace App\Controller\Movie;

use App\Entity\Media;
use App\Entity\Movie;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MovieController extends AbstractController
{
    #[Route(path: '/media/{id}', name: 'page_detail_media')]
    public function detail(
        Media $media
    ): Response
    {
        return $this->render('movie/detail.html.twig', [
            'media' => $media,
        ]);
    }

    #[Route(path: '/serie', name: 'page_detail_serie')]
    public function detailSerie(): Response
    {
        return $this->render(view: 'movie/detail_serie.html.twig');
    }
}
