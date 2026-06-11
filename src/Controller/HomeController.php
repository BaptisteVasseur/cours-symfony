<?php

namespace App\Controller;

use App\Entity\Listing;
use App\Repository\ListingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ListingRepository $listingRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'listings' => $listingRepository->findAll(),
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
