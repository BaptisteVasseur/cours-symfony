<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ListingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, ListingRepository $listingRepository): Response
    {
        $destination = $request->query->get('destination');
        $guests = $request->query->get('guests') !== null ? (int) $request->query->get('guests') : null;

        $checkIn = $this->parseDate($request->query->get('checkin'));
        $checkOut = $this->parseDate($request->query->get('checkout'));

        if ($checkIn !== null && $checkOut !== null && $checkOut <= $checkIn) {
            $checkIn = $checkOut = null;
        }

        $results = $listingRepository->search($destination, $checkIn, $checkOut, $guests);

        return $this->render('search/index.html.twig', [
            'results' => $results,
            'criteria' => [
                'destination' => $destination,
                'checkin' => $checkIn?->format('Y-m-d'),
                'checkout' => $checkOut?->format('Y-m-d'),
                'guests' => $guests,
            ],
        ]);
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->setTime(0, 0);
        } catch (\Exception) {
            return null;
        }
    }
}
