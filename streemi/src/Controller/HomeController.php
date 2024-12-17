<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\MediaRepository;
use App\Repository\MovieRepository;
use App\Repository\SerieRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
    )
    {
    }

    #[Route(path: '/', name: 'page_homepage')]
    public function home(
        MediaRepository $mediaRepository,
    ): Response
    {
        $medias = $mediaRepository->findPopular(maxResults: 9);

        return $this->render(view: 'index.html.twig', parameters: [
            'medias' => $medias,
        ]);
    }

    #[Route(path: '/test', name: 'page_test')]
    public function test(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): Response
    {
        /** @var User $user */
        $user = $userRepository->findOneBy([]);

        $user->setEmail('email' . random_int(1000, 9999) .'@example.com');
        $entityManager->flush();

        return new Response('ok');
    }
}
