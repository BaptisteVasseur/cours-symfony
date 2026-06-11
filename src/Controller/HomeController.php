<?php

namespace App\Controller;

use App\Entity\Listing;
use App\Repository\ListingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ListingRepository $listingRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'listings' => $listingRepository->findBy(['isActive' => true], ['pricePerNight' => 'ASC']),
        ]);
    }

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, ListingRepository $listingRepository): Response
    {
        $destination = trim($request->query->get('destination', ''));
        $checkinStr  = $request->query->get('checkin', '');
        $checkoutStr = $request->query->get('checkout', '');
        $guestsCount = (int) $request->query->get('guests', 0);

        $checkin  = $checkinStr  !== '' ? new \DateTime($checkinStr)  : null;
        $checkout = $checkoutStr !== '' ? new \DateTime($checkoutStr) : null;

        if ($checkin && $checkout && $checkout <= $checkin) {
            $checkout = null;
            $checkin  = null;
        }

        $listings = $listingRepository->findAvailable($destination, $checkin, $checkout, $guestsCount);

        return $this->render('home/search.html.twig', [
            'listings'    => $listings,
            'destination' => $destination,
            'checkin'     => $checkinStr,
            'checkout'    => $checkoutStr,
            'guests'      => $guestsCount ?: '',
        ]);
    }

    #[Route('/logement/{id}', name: 'app_listing_details', requirements: ['id' => '\d+'])]
    public function detail(Listing $listing): Response
    {
        return $this->render('home/listing.html.twig', [
            'listing' => $listing,
        ]);
    }
}
