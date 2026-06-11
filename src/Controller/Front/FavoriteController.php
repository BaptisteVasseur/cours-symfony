<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FavoriteController extends AbstractController
{
    #[Route('/logement/{id}/favori', name: 'app_property_favorite_toggle', methods: ['POST'])]
    public function toggle(Property $property, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Vous devez être connecté pour ajouter des favoris.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->getFavoriteProperties()->contains($property)) {
            $user->removeFavoriteProperty($property);
            $favorited = false;
        } else {
            $user->addFavoriteProperty($property);
            $favorited = true;
        }

        $entityManager->flush();

        return new JsonResponse([
            'favorited' => $favorited,
        ]);
    }
}
