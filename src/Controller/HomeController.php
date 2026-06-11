<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
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
            'properties' => $propertyRepository->findForListing('published'),
        ]);
    }

    #[Route('/logement/{id}', name: 'app_logement_detail')]
    public function detail(Property $property, PropertyRepository $propertyRepository): Response
    {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;

        return $this->render('home/logement.html.twig', [
            'property' => $property,
        ]);
    }

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, PropertyRepository $propertyRepository): Response
    {
        $destination = trim((string) $request->query->get('destination', ''));
        $checkinValue = (string) $request->query->get('checkin', '');
        $checkoutValue = (string) $request->query->get('checkout', '');
        $guests = max(1, $request->query->getInt('guests', 1));
        $checkin = $this->parseDate($checkinValue);
        $checkout = $this->parseDate($checkoutValue);
        $searchError = null;

        if ($checkin !== null && $checkout !== null && $checkout <= $checkin) {
            $searchError = 'La date de départ doit être postérieure à la date d’arrivée.';
            $checkin = null;
            $checkout = null;
        }

        return $this->render('home/search.html.twig', [
            'properties' => $propertyRepository->findForSearch($destination, $checkin, $checkout, $guests),
            'checkin' => $checkin,
            'checkout' => $checkout,
            'checkinValue' => $checkinValue,
            'checkoutValue' => $checkoutValue,
            'guests' => $guests,
            'destination' => $destination,
            'searchError' => $searchError,
        ]);
    }

    #[Route('/register', name: 'app_register')]
    public function register(): Response
    {
        return $this->render('home/register.html.twig');
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date : null;
    }
}
