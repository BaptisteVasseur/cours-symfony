<?php

namespace App\Controller;

use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search')]
    public function index(Request $request, PropertyRepository $propertyRepo): Response
    {
        $destination = $request->query->get('destination') ?: null;
        $checkInRaw  = $request->query->get('checkin') ?: null;
        $checkOutRaw = $request->query->get('checkout') ?: null;
        $guests      = $request->query->getInt('guests') ?: null;

        $checkIn = $checkInRaw
            ? \DateTimeImmutable::createFromFormat('Y-m-d', $checkInRaw) ?: null
            : null;
        $checkOut = $checkOutRaw
            ? \DateTimeImmutable::createFromFormat('Y-m-d', $checkOutRaw) ?: null
            : null;

        // Validate date range
        if ($checkIn && $checkOut && $checkOut <= $checkIn) {
            $this->addFlash('error', 'La date de départ doit être après la date d\'arrivée.');
            $checkIn = $checkOut = null;
        }

        $properties = $propertyRepo->findForSearch($destination, $checkIn, $checkOut, $guests);

        $nights = ($checkIn && $checkOut) ? $checkIn->diff($checkOut)->days : null;

        return $this->render('search/index.html.twig', [
            'properties'  => $properties,
            'destination' => $destination,
            'checkIn'     => $checkIn,
            'checkOut'    => $checkOut,
            'guests'      => $guests,
            'nights'      => $nights,
            'checkInRaw'  => $checkInRaw,
            'checkOutRaw' => $checkOutRaw,
        ]);
    }
}
