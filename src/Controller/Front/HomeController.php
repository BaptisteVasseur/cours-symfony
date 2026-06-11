<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
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
    public function detail(
        Property $property,
        PropertyRepository $propertyRepository,
        ReviewRepository $reviewRepository,
        ReservationRepository $reservationRepository,
        PropertyAvailabilityRepository $availabilityRepository,
    ): Response {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;
        $allReviews = $reviewRepository->findByPropertyOrdered($property);

        $unavailableDates = [];
        foreach ($availabilityRepository->findBlockedForProperty($property) as $avail) {
            $unavailableDates[$avail->getAvailableDate()->format('Y-m-d')] = true;
        }
        foreach ($reservationRepository->findConfirmedForProperty($property) as $reservation) {
            $d = $reservation->getCheckinDate();
            while ($d < $reservation->getCheckoutDate()) {
                $unavailableDates[$d->format('Y-m-d')] = true;
                $d = $d->modify('+1 day');
            }
        }

        return $this->render('front/property/show.html.twig', [
            'property' => $property,
            'reviews' => \array_slice($allReviews, 0, 5),
            'totalReviews' => \count($allReviews),
            'unavailableDates' => $unavailableDates,
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
        $checkin = $this->parseDate($request->query->get('checkin'));
        $checkout = $this->parseDate($request->query->get('checkout'));
        $guests = $request->query->getInt('guests');
        $destination = $request->query->get('destination');

        return $this->render('front/search/index.html.twig', [
            'properties' => $propertyRepository->search($destination, $checkin, $checkout, $guests),
            'checkin' => $checkin,
            'checkout' => $checkout,
            'guests' => $guests,
            'destination' => $destination,
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
