<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\PropertyVoter;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Interface hôte de gestion des disponibilités (énoncé §A.1) : calendrier
 * mensuel, blocage/déblocage de périodes (travaux, usage personnel) et gestion
 * du jeton d'export iCal (révocation).
 */
#[Route('/compte/logements/{id}/calendrier')]
#[IsGranted('ROLE_HOST')]
#[IsGranted(PropertyVoter::EDIT, subject: 'property')]
final class CalendarController extends AbstractController
{
    #[Route('', name: 'app_host_calendar', methods: ['GET'])]
    public function index(
        Property                       $property,
        Request                        $request,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository          $reservationRepository,
    ): Response
    {
        $firstOfMonth = $this->resolveMonth($request->query->get('month'));
        $monthStart = $firstOfMonth;
        $monthEnd = $firstOfMonth->modify('last day of this month');
        $rangeEndExclusive = $monthEnd->modify('+1 day');

        $availability = $availabilityRepository->findForRangeIndexed($property, $monthStart, $monthEnd);
        $confirmed = $reservationRepository->findConfirmedOverlapping($property, $monthStart, $rangeEndExclusive);

        // Ensemble des jours réservés (intervalle demi-ouvert [checkin, checkout)).
        $booked = [];
        foreach ($confirmed as $reservation) {
            $day = max($reservation->getCheckinDate(), $monthStart);
            $end = min($reservation->getCheckoutDate(), $rangeEndExclusive);
            while ($day < $end) {
                $booked[$day->format('Y-m-d')] = true;
                $day = $day->modify('+1 day');
            }
        }

        $days = [];
        $cursor = $monthStart;
        while ($cursor <= $monthEnd) {
            $key = $cursor->format('Y-m-d');
            $row = $availability[$key] ?? null;

            if (isset($booked[$key])) {
                $state = 'booked';
            } elseif ($row !== null && !$row->isAvailable()) {
                $state = 'blocked';
            } else {
                $state = 'available';
            }

            $days[] = [
                'date' => $cursor,
                'state' => $state,
                'price' => $row?->getPriceOverride(),
                'source' => $row?->getSource(),
            ];
            $cursor = $cursor->modify('+1 day');
        }

        $icalUrl = $property->getIcalExportToken() !== null
            ? $this->generateUrl(
                'app_property_calendar_ics',
                ['id' => $property->getId(), 'token' => $property->getIcalExportToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            : null;

        return $this->render('host/calendar/index.html.twig', [
            'property' => $property,
            'days' => $days,
            'leadingBlanks' => ((int)$monthStart->format('N')) - 1,
            'monthLabel' => $this->frenchMonthLabel($firstOfMonth),
            'currentMonth' => $firstOfMonth->format('Y-m'),
            'prevMonth' => $firstOfMonth->modify('-1 month')->format('Y-m'),
            'nextMonth' => $firstOfMonth->modify('+1 month')->format('Y-m'),
            'icalUrl' => $icalUrl,
        ]);
    }

    #[Route('/bloquer', name: 'app_host_calendar_block', methods: ['POST'])]
    public function block(
        Property                       $property,
        Request                        $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface         $entityManager,
    ): Response
    {
        return $this->toggleRange($property, $request, $availabilityRepository, $entityManager, blocked: true);
    }

    #[Route('/debloquer', name: 'app_host_calendar_unblock', methods: ['POST'])]
    public function unblock(
        Property                       $property,
        Request                        $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface         $entityManager,
    ): Response
    {
        return $this->toggleRange($property, $request, $availabilityRepository, $entityManager, blocked: false);
    }

    #[Route('/token', name: 'app_host_calendar_regenerate_token', methods: ['POST'])]
    public function regenerateToken(Property $property, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('calendar_token' . $property->getId(), (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $property->regenerateIcalExportToken();
        $entityManager->flush();

        $this->addFlash('success', 'Le lien iCal a été régénéré. L\'ancien lien n\'est plus valide.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    private function toggleRange(
        Property                       $property,
        Request                        $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface         $entityManager,
        bool                           $blocked,
    ): Response
    {
        if (!$this->isCsrfTokenValid('calendar' . $property->getId(), (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $start = $this->parseDate($request->request->get('start'));
        $end = $this->parseDate($request->request->get('end'));

        if ($start === null || $end === null || $start > $end) {
            $this->addFlash('error', 'Veuillez indiquer une période valide.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $existing = $availabilityRepository->findForRangeIndexed($property, $start, $end);
        $cursor = $start;
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $row = $existing[$key] ?? null;
            if ($row === null) {
                if (!$blocked) {
                    $cursor = $cursor->modify('+1 day');
                    continue; // rien à débloquer : jour déjà disponible par défaut
                }
                $row = new PropertyAvailability();
                $row->setProperty($property);
                $row->setAvailableDate($cursor);
                $entityManager->persist($row);
            }
            $row->setIsAvailable(!$blocked);
            $row->setSource('host');
            $cursor = $cursor->modify('+1 day');
        }

        $entityManager->flush();
        $this->addFlash('success', $blocked ? 'Période bloquée.' : 'Période débloquée.');

        return $this->redirectToRoute('app_host_calendar', [
            'id' => $property->getId(),
            'month' => $start->format('Y-m'),
        ]);
    }

    private function resolveMonth(mixed $month): DateTimeImmutable
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01');
            if ($parsed !== false) {
                return $parsed->setTime(0, 0);
            }
        }

        return new DateTimeImmutable('first day of this month')->setTime(0, 0);
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date->setTime(0, 0) : null;
    }

    private function frenchMonthLabel(DateTimeImmutable $date): string
    {
        $months = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $months[(int)$date->format('n')] . ' ' . $date->format('Y');
    }
}
