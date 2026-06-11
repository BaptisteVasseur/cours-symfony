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
    public function search(Request $request, PropertyRepository $propertyRepository, \App\Service\PropertyAvailabilityService $propertyAvailabilityService): Response
    {
        $checkin = $this->parseDate($request->query->get('checkin'));
        $checkout = $this->parseDate($request->query->get('checkout'));

        $destination = trim((string) $request->query->get('destination', ''));
        $guests = $request->query->getInt('guests', 0);

        $qb = $propertyRepository->createQueryBuilder('p')
            ->addSelect('m', 'a', 'r', 'host', 'hostProfile')
            ->leftJoin('p.media', 'm')
            ->leftJoin('p.address', 'a')
            ->leftJoin('p.reviews', 'r')
            ->leftJoin('p.host', 'host')
            ->leftJoin('host.profile', 'hostProfile')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('p.createdAt', 'DESC');

        if ($destination !== '') {
            $qb->andWhere('LOWER(a.city) LIKE :dest OR LOWER(p.title) LIKE :dest')
                ->setParameter('dest', '%' . mb_strtolower($destination) . '%');
        }

        if ($guests > 0) {
            $qb->andWhere('p.maxGuests >= :guests')
                ->setParameter('guests', $guests);
        }

        $properties = $qb->getQuery()->getResult();

        // If both dates provided, strict availability filtering
        if ($checkin instanceof \DateTimeImmutable && $checkout instanceof \DateTimeImmutable) {
            $available = [];
            foreach ($properties as $property) {
                if ($propertyAvailabilityService->isAvailable($property, $checkin, $checkout, $guests)) {
                    $available[] = $property;
                }
            }
            $properties = $available;
        }

        return $this->render('front/search/index.html.twig', [
            'properties' => $properties,
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
