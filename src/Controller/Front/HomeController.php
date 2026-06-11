<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\FavoriteRepository;
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
    public function index(PropertyRepository $propertyRepository, FavoriteRepository $favoriteRepository): Response
    {
        $user = $this->getUser();
        $favoriteIds = $user instanceof User ? $favoriteRepository->findPropertyIdsByUser($user) : [];

        return $this->render('front/home/index.html.twig', [
            'properties'  => $propertyRepository->findForListing('published'),
            'favoriteIds' => $favoriteIds,
        ]);
    }

    #[Route('/logement/{id}', name: 'app_logement_detail')]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(PropertyVoter::VIEW, subject: 'property')]
    public function detail(
        Property $property,
        Request $request,
        PropertyRepository $propertyRepository,
        ReviewRepository $reviewRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;
        $allReviews = $reviewRepository->findByPropertyOrdered($property);

        $reviewableReservation = null;
        $user = $this->getUser();
        if ($user instanceof User) {
            $reviewableReservation = $reservationRepository->findCompletedWithoutReview($user, $property);
        }

        return $this->render('front/property/show.html.twig', [
            'property'              => $property,
            'reviews'               => \array_slice($allReviews, 0, 5),
            'totalReviews'          => \count($allReviews),
            'reviewableReservation' => $reviewableReservation,
            'checkin'               => $request->query->get('checkin', ''),
            'checkout'              => $request->query->get('checkout', ''),
            'guests'                => $request->query->getInt('guests', 0),
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

}
