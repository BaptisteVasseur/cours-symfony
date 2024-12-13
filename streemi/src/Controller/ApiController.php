<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ApiController extends AbstractController
{
//    #[Route('/api/users', name: 'api_list_users', methods: ['GET'])]
//    public function myList(
//        UserRepository $userRepository,
//        SerializerInterface $serializer,
//    ): Response
//    {
//        $users = $userRepository->findAll();
//
//        $result = $serializer->serialize($users, format: 'json', context: [
//            'groups' => 'group_user_list',
//        ]);
//
//        return new JsonResponse($result);
//    }

//    #[Route('/api/users/{id}', name: 'api_get_user', methods: ['GET'])]
//    public function show(User $user, SerializerInterface $serializer,): JsonResponse
//    {
//        $result = $serializer->serialize($user, format: 'json', context: [
//            'groups' => 'group_user_show',
//        ]);
//
//        return new JsonResponse($result);
//    }

//    #[Route('/api/users', name: 'api_create_user', methods: ['POST'])]
//    public function create(
//        SerializerInterface $serializer,
//        Request $request,
//        EntityManagerInterface $entityManager
//    ): JsonResponse
//    {
//        $data = $request->getContent();
//        $user = $serializer->deserialize($data, type: User::class, format: 'json', context: [
//            'groups' => 'group_user_create',
//        ]);
//
//        $entityManager->persist($user);
//        $entityManager->flush();
//
//        return new JsonResponse($user->getId(), Response::HTTP_CREATED);
//    }

//    #[Route('/api/users', name: 'api_create_user', methods: ['POST'])]
//    public function create(
//        #[MapRequestPayload(serializationContext: ['groups' => 'group_user_create'])] User $user,
//        EntityManagerInterface $entityManager,
//    ): JsonResponse
//    {
//        $entityManager->persist($user);
//        $entityManager->flush();
//
//        return new JsonResponse($user->getId(), Response::HTTP_CREATED);
//    }
}
