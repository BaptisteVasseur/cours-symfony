<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\User;
use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host/properties')]
#[IsGranted('ROLE_HOST')]
final class PropertyController extends AbstractController
{
    #[Route('', name: 'app_host_property_index', methods: ['GET'])]
    public function index(PropertyRepository $propertyRepository): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $properties = $propertyRepository->findBy(
            ['host' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('host/properties.html.twig', [
            'properties' => $properties,
        ]);
    }
}
