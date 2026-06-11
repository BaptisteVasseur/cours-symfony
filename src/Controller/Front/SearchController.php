<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function index(Request $request, PropertyRepository $propertyRepository): Response
    {
        $destination = trim((string) $request->query->get('destination', ''));
        $checkinStr = (string) $request->query->get('checkin', '');
        $checkoutStr = (string) $request->query->get('checkout', '');
        $guests = (int) $request->query->get('guests', 0);

        $checkin = null;
        $checkout = null;

        if ($checkinStr !== '') {
            try {
                $checkin = new \DateTimeImmutable($checkinStr);
            } catch (\Exception) {
                $checkin = null;
            }
        }

        if ($checkoutStr !== '') {
            try {
                $checkout = new \DateTimeImmutable($checkoutStr);
            } catch (\Exception) {
                $checkout = null;
            }
        }

        if ($checkin !== null && $checkout !== null && $checkin >= $checkout) {
            $checkout = null;
            $checkin = null;
        }

        $properties = $propertyRepository->findAvailableWithFilters(
            $destination !== '' ? $destination : null,
            $checkin,
            $checkout,
            $guests > 0 ? $guests : null,
        );

        return $this->render('front/search/index.html.twig', [
            'properties'   => $properties,
            'destination'  => $destination,
            'checkin'      => $checkin,
            'checkout'     => $checkout,
            'checkinStr'   => $checkin?->format('Y-m-d') ?? '',
            'checkoutStr'  => $checkout?->format('Y-m-d') ?? '',
            'guests'       => $guests,
        ]);
    }
}
