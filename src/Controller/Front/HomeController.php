<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Repository\PropertyRepository;
use App\Repository\ReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\PropertyVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        return $this->render('front/home/index.html.twig', [
            'properties' => $propertyRepository->findForListing('published'),
        ]);
    }

    #[Route('/logement/{id}', name: 'app_logement_detail')]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(PropertyVoter::VIEW, subject: 'property')]
    public function detail(Property $property, PropertyRepository $propertyRepository, ReviewRepository $reviewRepository): Response
    {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;
        $allReviews = $reviewRepository->findByPropertyOrdered($property);

        $blockedDates = array_map(
            fn(PropertyAvailability $a) => $a->getOccupiedDate()->format('Y-m-d'),
            $property->getAvailabilities()->toArray()
        );

        return $this->render('front/property/show.html.twig', [
            'property' => $property,
            'reviews' => \array_slice($allReviews, 0, 5),
            'totalReviews' => \count($allReviews),
            'blockedDates' => $blockedDates,
        ]);
    }

    #[Route('/logement/{id}/calendar', name: 'app_logement_calendar', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(PropertyVoter::VIEW, subject: 'property')]
    public function calendar(Property $property, Request $request): Response
    {
        $year  = max(2020, min(2040, (int) ($request->query->get('year') ?: (int) date('Y'))));
        $month = max(1, min(12, (int) ($request->query->get('month') ?: (int) date('n'))));
        $checkin  = $this->parseDate($request->query->get('checkin'));
        $checkout = $this->parseDate($request->query->get('checkout'));

        $blockedDates = array_map(
            fn(PropertyAvailability $a) => $a->getOccupiedDate()->format('Y-m-d'),
            $property->getAvailabilities()->toArray()
        );
        $blockedSet = array_flip($blockedDates);

        $cutoffDate = null;
        if ($checkin && !$checkout) {
            $cursor = $checkin->modify('+1 day');
            for ($i = 0; $i < 730; $i++) {
                if (isset($blockedSet[$cursor->format('Y-m-d')])) {
                    $cutoffDate = $cursor;
                    break;
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        $today       = new \DateTimeImmutable('today');
        $month1Start = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $month2Start = $month1Start->modify('+1 month');

        return $this->render('front/property/partials/calendar.html.twig', [
            'month1' => $this->buildMonthData($month1Start, $today, $blockedSet, $checkin, $checkout, $cutoffDate),
            'month2' => $this->buildMonthData($month2Start, $today, $blockedSet, $checkin, $checkout, $cutoffDate),
        ]);
    }

    private function buildMonthData(
        \DateTimeImmutable $start,
        \DateTimeImmutable $today,
        array $blockedSet,
        ?\DateTimeImmutable $checkin,
        ?\DateTimeImmutable $checkout,
        ?\DateTimeImmutable $cutoffDate,
    ): array {
        static $frMonths = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        $year     = (int) $start->format('Y');
        $month    = (int) $start->format('n');
        $lastDay  = (int) $start->format('t');
        $firstDow = (int) $start->format('N') - 1; // lundi = 0

        $days = array_fill(0, $firstDow, null);

        for ($d = 1; $d <= $lastDay; $d++) {
            $current  = new \DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $month, $d));
            $dateStr  = $current->format('Y-m-d');
            $isPast   = $current < $today;
            $isBlocked      = isset($blockedSet[$dateStr]);
            $isBeyondCutoff = !$isBlocked && $cutoffDate !== null && $current >= $cutoffDate;
            $isCheckin  = $checkin  !== null && $dateStr === $checkin->format('Y-m-d');
            $isCheckout = $checkout !== null && $dateStr === $checkout->format('Y-m-d');
            $inRange    = $checkin !== null && $checkout !== null && $current > $checkin && $current < $checkout;

            $days[] = [
                'date'          => $dateStr,
                'day'           => $d,
                'selectable'    => !$isPast && !$isBlocked && !$isBeyondCutoff,
                'isBlocked'     => $isBlocked,
                'isCheckin'     => $isCheckin,
                'isCheckout'    => $isCheckout,
                'inRange'       => $inRange,
                'rangeRight'    => $isCheckin  && $checkout !== null,
                'rangeLeft'     => $isCheckout && $checkin  !== null,
            ];
        }

        return [
            'label' => $frMonths[$month] . ' ' . $year,
            'days'  => $days,
        ];
    }

    #[Route('/logement/{id}/avis', name: 'app_logement_reviews')]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(PropertyVoter::VIEW, subject: 'property')]
    public function reviews(Property $property, PropertyRepository $propertyRepository, ReviewRepository $reviewRepository): Response
    {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;

        return $this->render('front/property/reviews.html.twig', [
            'property' => $property,
            'reviews' => $reviewRepository->findByPropertyOrdered($property),
        ]);
    }

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, PropertyRepository $propertyRepository): Response
    {
        $destination = trim((string) $request->query->get('destination', ''));
        $checkin     = $this->parseDate($request->query->get('checkin'));
        $checkout    = $this->parseDate($request->query->get('checkout'));
        $guests      = max(1, $request->query->getInt('guests', 1));

        $properties = $propertyRepository->findForSearch($destination, $checkin, $checkout, $guests);

        return $this->render('front/search/index.html.twig', [
            'properties'  => $properties,
            'destination' => $destination,
            'checkin'     => $checkin?->format('Y-m-d') ?? '',
            'checkout'    => $checkout?->format('Y-m-d') ?? '',
            'guests'      => $guests,
            'checkinDate'  => $checkin,
            'checkoutDate' => $checkout,
            'total'        => count($properties),
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
