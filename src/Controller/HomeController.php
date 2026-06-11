<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, PropertyRepository $propertyRepository): Response
    {
        $type   = $request->query->get('type');
        $search = $request->query->getString('q');
        $search = $search !== '' ? $search : null;

        return $this->render('home/index.html.twig', [
            'properties' => $propertyRepository->findForListing('published', $type ?: null, $search),
            'activeType' => $type,
            'search'     => $search ?? '',
        ]);
    }

    #[Route('/logement/{id}', name: 'app_logement_detail')]
    public function detail(Property $property, Request $request, PropertyRepository $propertyRepository, ReservationRepository $reservationRepository): Response
    {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;
        $checkin  = $this->parseDate($request->query->get('checkin'));
        $checkout = $this->parseDate($request->query->get('checkout'));
        $guests   = $request->query->getInt('guests', 1);

        // Availability map: Y-m-d → state from PropertyAvailability rows
        $availabilityMap = [];
        foreach ($property->getAvailabilities() as $av) {
            $key = $av->getAvailableDate()?->format('Y-m-d');
            if ($key !== null) {
                $availabilityMap[$key] = [
                    'available'     => $av->isAvailable(),
                    'priceOverride' => $av->getPriceOverride(),
                    'minimumStay'   => $av->getMinimumStay(),
                    'blockReason'   => $av->getBlockReason(),
                ];
            }
        }

        // Mark confirmed reservation days as booked
        $reservedMap = [];
        foreach ($reservationRepository->findConfirmedByProperty($property) as $res) {
            $d = $res->getCheckinDate();
            $end = $res->getCheckoutDate();
            while ($d < $end) {
                $reservedMap[$d->format('Y-m-d')] = true;
                $d = $d->modify('+1 day');
            }
        }

        // 12 months starting from current month
        $base     = new \DateTimeImmutable('first day of this month');
        $frMonths = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        $calendarMonths = [];
        for ($i = 0; $i < 12; $i++) {
            $first = $base->modify("+{$i} months");
            $calendarMonths[] = [
                'year'         => (int) $first->format('Y'),
                'month'        => (int) $first->format('n'),
                'daysInMonth'  => (int) $first->format('t'),
                'firstWeekday' => (int) $first->format('N'),
                'label'        => $frMonths[(int) $first->format('n') - 1] . ' ' . $first->format('Y'),
            ];
        }

        return $this->render('home/logement.html.twig', [
            'property'        => $property,
            'availabilityMap' => $availabilityMap,
            'reservedMap'     => $reservedMap,
            'calendarMonths'  => $calendarMonths,
            'checkin'         => $checkin,
            'checkout'        => $checkout,
            'guests'          => $guests,
        ]);
    }

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, PropertyRepository $propertyRepository): Response
    {
        $destination = $request->query->get('destination');
        $checkin     = $this->parseDate($request->query->get('checkin'));
        $checkout    = $this->parseDate($request->query->get('checkout'));
        $guests      = $request->query->getInt('guests', 1);

        $hasFilters = $destination || $checkin || $checkout || $guests > 1;

        $properties = $hasFilters
            ? $propertyRepository->findAvailableForSearch($destination, $checkin, $checkout, $guests)
            : $propertyRepository->findForListing('published');

        return $this->render('home/search.html.twig', [
            'properties'  => $properties,
            'checkin'     => $checkin,
            'checkout'    => $checkout,
            'guests'      => $guests,
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
