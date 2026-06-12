<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use App\Repository\ReviewRepository;
use App\Service\Availability\AvailabilityChecker;
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
    public function detail(Request $request, Property $property, PropertyRepository $propertyRepository, ReviewRepository $reviewRepository): Response
    {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;
        $allReviews = $reviewRepository->findByPropertyOrdered($property);

        return $this->render('front/property/show.html.twig', [
            'property' => $property,
            'reviews' => \array_slice($allReviews, 0, 5),
            'totalReviews' => \count($allReviews),
            'checkin' => $this->parseDate($request->query->get('checkin')),
            'checkout' => $this->parseDate($request->query->get('checkout')),
            'guests' => $request->query->getInt('guests'),
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
    public function search(
        Request $request,
        PropertyRepository $propertyRepository,
        AvailabilityChecker $availabilityChecker,
    ): Response {
        $destination = trim((string) $request->query->get('destination', ''));
        $guests = $request->query->getInt('guests');
        $checkin = $this->parseDate($request->query->get('checkin'));
        $checkout = $this->parseDate($request->query->get('checkout'));

        $properties = $propertyRepository->searchPublished(
            $destination !== '' ? $destination : null,
            $guests > 0 ? $guests : null,
        );

        // Filtre de disponibilité stricte : réutilise A.2 (jours bloqués + chevauchement confirmé).
        if (
            $checkin !== null
            && $checkout !== null
            && $checkout > $checkin
            && $checkin >= new \DateTimeImmutable('today')
        ) {
            $guestsForCheck = max(1, $guests);
            $properties = array_values(array_filter(
                $properties,
                static fn (Property $property): bool => $availabilityChecker
                    ->check($property, $checkin, $checkout, $guestsForCheck)
                    ->isAvailable(),
            ));
        }

        return $this->render('front/search/index.html.twig', [
            'properties' => $properties,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'guests' => $guests,
            'destination' => $destination !== '' ? $destination : null,
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
}
