<?php

declare(strict_types=1);

namespace App\Controller\Movie;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\MediaRepository;
use App\Repository\MovieRepository;
use App\Repository\SerieRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CategoryController extends AbstractController
{
    #[Route('/discover', name: 'movie_discover')]
    public function index(
        CategoryRepository $categoryRepository,
    ): Response
    {
        $categories = $categoryRepository->findAll();

        return $this->render('movie/discover.html.twig', [
            'categories' => $categories
        ]);
    }

    #[Route('/discover/{id}', name: 'show_category')]
    public function show(
        Category $category
    ): Response
    {
        return $this->render('movie/category.html.twig', [
            'category' => $category,
        ]);
    }
}
