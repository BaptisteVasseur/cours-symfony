<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function __invoke(
        MediaRepository $repository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $lastWatched = $repository->findOneBy([], ['id' => 'DESC']);

        return $this->render('index.html.twig', [
            'medias' => $repository->findPopular(),
            'lastWatched' => $lastWatched,
        ]);
    }
}
