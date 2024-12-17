<?php

declare(strict_types=1);

namespace App\Controller\API;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class UserApiController extends AbstractController
{
    #[Route('/api/users', name: 'api_user', methods: ['GET'])]
    public function index(): Response
    {
    }
}
