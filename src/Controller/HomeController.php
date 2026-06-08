<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'properties' => $propertyRepository->findForListing(),
        ]);
    }

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, PropertyRepository $propertyRepository): Response
    {
        $checkin = $this->parseDate($request->query->get('checkin'));
        $checkout = $this->parseDate($request->query->get('checkout'));

        return $this->render('home/search.html.twig', [
            'properties' => $propertyRepository->findForListing(),
            'checkin' => $checkin,
            'checkout' => $checkout,
            'guests' => $request->query->getInt('guests'),
            'destination' => $request->query->get('destination'),
        ]);
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date : null;
    }

    #[Route('/register', name: 'app_register')]
    public function register(): Response
    {
        return $this->render('home/register.html.twig');
    }
}
