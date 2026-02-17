<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        $properties = $propertyRepository->findAll();

        return $this->render('home/index.html.twig', [
            'properties' => $properties,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/property/{id}', name: 'app_property_detail')]
    public function detail(Property $property): Response
    {
        return $this->render('home/detail.html.twig', [
            'property' => $property,
        ]);
    }

    #[Route('/host/{id}', name: 'app_host_profile')]
    public function hostProfile(User $host): Response
    {
        return $this->render('home/host.html.twig', [
            'host' => $host,
        ]);
    }
}
