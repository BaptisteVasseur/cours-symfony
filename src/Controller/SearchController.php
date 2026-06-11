<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    /**
     * Moteur de recherche voyageur (Partie C) : destination, dates, voyageurs.
     */
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, PropertyRepository $propertyRepository): Response
    {
        $destination = $request->query->get('destination');
        $guests = $request->query->getInt('guests') ?: null;
        [$checkin, $checkout] = $this->parseDates($request);

        $results = $propertyRepository->search($destination, $checkin, $checkout, $guests);

        return $this->render('pages/search/index.html.twig', [
            'results' => $results,
            'destination' => $destination,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'guests' => $guests,
        ]);
    }

    /**
     * @return array{0:?\DateTimeImmutable, 1:?\DateTimeImmutable}
     */
    private function parseDates(Request $request): array
    {
        $rawIn = (string) $request->query->get('checkin');
        $rawOut = (string) $request->query->get('checkout');
        if ($rawIn === '' || $rawOut === '') {
            return [null, null];
        }

        try {
            $checkin = (new \DateTimeImmutable($rawIn))->setTime(0, 0, 0);
            $checkout = (new \DateTimeImmutable($rawOut))->setTime(0, 0, 0);
        } catch (\Exception) {
            return [null, null];
        }

        return $checkout > $checkin ? [$checkin, $checkout] : [null, null];
    }
}
