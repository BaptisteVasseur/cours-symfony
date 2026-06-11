<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
//    #[IsGranted('ROLE_USER')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        if ($this->isGranted('ROLE_BANNED')) {
            return $this->render('security/banned.html.twig');
        }

        $properties = $propertyRepository->findAll();
        return $this->render('home/index.html.twig', [
            'properties' => $properties,
        ]);
    }

    #[Route('/form', name: 'app_form')]
    public function form(): Response
    {
        return $this->render('form/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }
}
