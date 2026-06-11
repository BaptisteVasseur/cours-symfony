<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use App\Repository\ReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\PropertyVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        return $this->render('front/home/index.html.twig', [
            'properties' => $propertyRepository->findForListing('published'),
        ]);
    }

    #[Route('/logement/{id}', name: 'app_logement_detail')]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(PropertyVoter::VIEW, subject: 'property')]
    public function detail(Property $property, PropertyRepository $propertyRepository, ReviewRepository $reviewRepository): Response
    {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;
        $allReviews = $reviewRepository->findByPropertyOrdered($property);

        return $this->render('front/property/show.html.twig', [
            'property' => $property,
            'reviews' => \array_slice($allReviews, 0, 5),
            'totalReviews' => \count($allReviews),
        ]);
    }

    #[Route('/logement/{id}/avis', name: 'app_logement_reviews')]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(PropertyVoter::VIEW, subject: 'property')]
    public function reviews(Property $property, PropertyRepository $propertyRepository, ReviewRepository $reviewRepository): Response
    {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;

        return $this->render('front/property/reviews.html.twig', [
            'property' => $property,
            'reviews' => $reviewRepository->findByPropertyOrdered($property),
        ]);
    }

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, PropertyRepository $propertyRepository): Response
    {
        $destination = trim((string) $request->query->get('destination', ''));
        $checkinValue = trim((string) $request->query->get('checkin', ''));
        $checkoutValue = trim((string) $request->query->get('checkout', ''));
        $checkin = $this->parseDate($checkinValue);
        $checkout = $this->parseDate($checkoutValue);
        $guests = max(1, $request->query->getInt('guests', 1));
        $dateError = null;
        $properties = [];

        if ($checkinValue !== '' || $checkoutValue !== '') {
            if ($checkin === null || $checkout === null) {
                $dateError = 'Les dates d’arrivée et de départ doivent être renseignées ensemble au format valide.';
            } elseif ($checkin >= $checkout) {
                $dateError = 'La date de départ doit être postérieure à la date d’arrivée.';
            }
        }

        if ($dateError === null) {
            $properties = $propertyRepository->searchAvailable(
                $destination !== '' ? $destination : null,
                $checkin,
                $checkout,
                $guests,
            );
        }

        return $this->render('front/search/index.html.twig', [
            'properties' => $properties,
            'checkin' => $checkin?->format('Y-m-d') ?? $checkinValue,
            'checkout' => $checkout?->format('Y-m-d') ?? $checkoutValue,
            'guests' => $guests,
            'destination' => $destination,
            'dateError' => $dateError,
        ]);
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
