<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\RealtimeTokenFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RealtimeController extends AbstractController
{
    #[Route('/realtime/token', name: 'app_realtime_token', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function token(RealtimeTokenFactory $tokenFactory): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'token' => $tokenFactory->create($user),
            'expiresIn' => 300,
        ]);
    }
}
