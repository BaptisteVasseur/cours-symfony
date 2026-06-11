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
    public function search(
        Request $request,
        PropertyRepository $propertyRepository,
        AvailabilityChecker $availabilityChecker,
    ): Response {
        $checkin = $this->parseDate($request->query->get('checkin'));
        $checkout = $this->parseDate($request->query->get('checkout'));
        $guests = $this->parsePositiveInt($request->query->get('guests'));
        $destination = $request->query->get('destination');
        $priceMin = $this->parsePositiveInt($request->query->get('priceMin'));
        $priceMax = $this->parsePositiveInt($request->query->get('priceMax'));
        $propertyType = $request->query->get('propertyType');
        $sort = $request->query->get('sort');

        $properties = $propertyRepository->searchPublished([
            'destination' => $destination,
            'guests' => $guests,
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
            'propertyType' => $propertyType,
            'sort' => $sort,
        ]);

        if ($checkin instanceof \DateTimeImmutable && $checkout instanceof \DateTimeImmutable && $checkin < $checkout) {
            $effectiveGuests = $guests ?? 1;
            $properties = array_values(array_filter(
                $properties,
                static fn (Property $property): bool => $availabilityChecker->check($property, $checkin, $checkout, $effectiveGuests)->available,
            ));
        }

        return $this->render('front/search/index.html.twig', [
            'properties' => $properties,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'guests' => $guests ?? 0,
            'destination' => $destination,
            'priceMin' => $priceMin ?? 0,
            'priceMax' => $priceMax ?? 0,
            'propertyType' => $propertyType,
            'sort' => $sort,
        ]);
    }

    private function parsePositiveInt(?string $value): ?int
    {
        if ($value === null || $value === '' || !ctype_digit($value)) {
            return null;
        }
        $int = (int) $value;

        return $int > 0 ? $int : null;
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
