<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Favorite;
use App\Entity\Property;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/favoris')]
#[IsGranted('ROLE_USER')]
final class FavoriteController extends AbstractController
{
    #[Route('/toggle/{id}', name: 'app_favorite_toggle', methods: ['POST'])]
    public function toggle(
        Property $property,
        Request $request,
        FavoriteRepository $favoriteRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('fav_'.$property->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $existing = $favoriteRepository->findOneByUserAndProperty($user, $property);

        if ($existing !== null) {
            $em->remove($existing);
            $em->flush();
            return new JsonResponse(['status' => 'removed']);
        }

        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setProperty($property);
        $em->persist($favorite);
        $em->flush();

        return new JsonResponse(['status' => 'added']);
    }

    #[Route('', name: 'app_favorites_index', methods: ['GET'])]
    public function index(FavoriteRepository $favoriteRepository): \Symfony\Component\HttpFoundation\Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $favorites = $favoriteRepository->createQueryBuilder('f')
            ->addSelect('p', 'm', 'a')
            ->join('f.property', 'p')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('front/favorite/index.html.twig', [
            'favorites' => $favorites,
        ]);
    }
}
