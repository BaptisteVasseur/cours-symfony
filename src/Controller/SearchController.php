<?php

namespace App\Controller;

use App\Dto\SearchCriteriaDto;
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
        $guests      = $request->query->getInt('guests') ?: null;

        $checkInRaw  = $request->query->get('checkin') ?: null;
        $checkOutRaw = $request->query->get('checkout') ?: null;

        $checkIn  = $checkInRaw  ? (\DateTimeImmutable::createFromFormat('Y-m-d', $checkInRaw)  ?: null) : null;
        $checkOut = $checkOutRaw ? (\DateTimeImmutable::createFromFormat('Y-m-d', $checkOutRaw) ?: null) : null;

        if ($checkIn && $checkOut && $checkOut <= $checkIn) {
            $this->addFlash('error', 'La date de départ doit être après la date d\'arrivée.');
            $checkIn = $checkOut = null;
        }

        $criteria = new SearchCriteriaDto($destination, $checkIn, $checkOut, $guests);

        $properties = $propertyRepo->findForSearch(
            $criteria->destination,
            $criteria->checkIn,
            $criteria->checkOut,
            $criteria->guests,
        );

        return $this->render('search/index.html.twig', [
            'properties' => $properties,
            'criteria'   => $criteria,
        ]);
    }
}
