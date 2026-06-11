<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\FavoriteRepository;
use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function index(Request $request, PropertyRepository $propertyRepository, FavoriteRepository $favoriteRepository): Response
    {
        $destination = $request->query->get('destination', '');
        $checkinStr  = $request->query->get('checkin', '');
        $checkoutStr = $request->query->get('checkout', '');
        $guests      = $request->query->getInt('guests', 0);

        $checkin  = null;
        $checkout = null;

        try {
            if ($checkinStr !== '') {
                $checkin = new \DateTimeImmutable($checkinStr);
            }
            if ($checkoutStr !== '') {
                $checkout = new \DateTimeImmutable($checkoutStr);
            }
        } catch (\Exception) {
            $checkin  = null;
            $checkout = null;
        }

        if ($checkin !== null && $checkout !== null && $checkout <= $checkin) {
            $checkout = null;
            $checkin  = null;
        }

        $properties = $propertyRepository->search(
            $destination ?: null,
            $checkin,
            $checkout,
            $guests > 0 ? $guests : null,
        );

        $user = $this->getUser();
        $favoriteIds = $user instanceof User ? $favoriteRepository->findPropertyIdsByUser($user) : [];

        return $this->render('front/search/index.html.twig', [
            'properties'  => $properties,
            'destination' => $destination,
            'checkin'     => $checkinStr,
            'checkout'    => $checkoutStr,
            'guests'      => $guests ?: 1,
            'favoriteIds' => $favoriteIds,
        ]);
    }
}
