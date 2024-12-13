<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function __invoke(MediaRepository $repository, SerializerInterface $serializer): Response
    {
//        /** @var User $user */
//        $user = $entityManager->getRepository(User::class)->findOneBy([]);
//        $user->setEmail('test' . random_int(0, 9999) . '@gmail.com');
//        $entityManager->flush();

        $lastWatched = $repository->findOneBy([], ['id' => 'DESC']);

        return $this->render('index.html.twig', [
            'medias' => $repository->findPopular(),
            'lastWatched' => $lastWatched,
        ]);
    }
}
