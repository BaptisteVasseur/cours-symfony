<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\PropertyRepository;
use App\Service\AvailabilityChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(
        Request $request,
        PropertyRepository $propertyRepository,
        AvailabilityChecker $availabilityChecker,
    ): Response {
        $destination = $request->query->getString('destination', '');
        $checkinStr = $request->query->getString('checkin', '');
        $checkoutStr = $request->query->getString('checkout', '');
        $guestsStr = $request->query->getString('guests', '1');

        $results = [];
        $errors = [];

        // Validate and parse inputs
        try {
            $guests = (int) $guestsStr;
            if ($guests < 1) {
                $errors[] = 'Le nombre de voyageurs doit être au moins 1.';
            }

            $checkin = $checkoutDate = null;
            if ($checkinStr && $checkoutStr) {
                try {
                    $checkin = \DateTimeImmutable::createFromFormat('Y-m-d', $checkinStr);
                    $checkout = \DateTimeImmutable::createFromFormat('Y-m-d', $checkoutStr);

                    if ($checkin === false || $checkout === false) {
                        $errors[] = 'Veuillez entrer des dates au format valide.';
                    } elseif ($checkin >= $checkout) {
                        $errors[] = 'La date de départ doit être postérieure à la date d\'arrivée.';
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Dates invalides.';
                }
            }

            // Search properties if no errors
            if (empty($errors)) {
                $query = $propertyRepository->createQueryBuilder('p')
                    ->addSelect('a', 'm', 'h')
                    ->leftJoin('p.address', 'a')
                    ->leftJoin('p.media', 'm')
                    ->leftJoin('p.host', 'h')
                    ->andWhere('p.status = :status')
                    ->setParameter('status', 'published');

                // Filter by destination (city/address)
                if ($destination !== '') {
                    $query->andWhere('(LOWER(p.title) LIKE LOWER(:destination) OR LOWER(a.city) LIKE LOWER(:destination) OR LOWER(a.country) LIKE LOWER(:destination))')
                        ->setParameter('destination', '%' . $destination . '%');
                }

                // Filter by capacity
                if ($guests > 1) {
                    $query->andWhere('p.maxGuests >= :guests')
                        ->setParameter('guests', $guests);
                }

                $allProperties = $query->getQuery()->getResult();

                // Filter by availability if dates are provided
                if ($checkin !== null && $checkout !== null) {
                    foreach ($allProperties as $property) {
                        if ($availabilityChecker->isAvailable($property, $checkin, $checkout, $guests)) {
                            $results[] = $property;
                        }
                    }
                } else {
                    $results = $allProperties;
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'Une erreur est survenue lors de la recherche.';
        }

        return $this->render('front/search/results.html.twig', [
            'results' => $results,
            'destination' => $destination,
            'checkin' => $checkinStr,
            'checkout' => $checkoutStr,
            'guests' => $guestsStr,
            'errors' => $errors,
        ]);
    }
}
