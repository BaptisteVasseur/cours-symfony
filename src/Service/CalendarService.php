<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion du calendrier d'un logement par l'hôte (Partie A) :
 * blocage/déblocage manuel de nuits, tarif journalier, durée de séjour minimum,
 * et construction de la vue mensuelle.
 *
 * Modèle « un jour = une ligne » (PropertyAvailability) : voir conception.txt.
 */
final readonly class CalendarService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PropertyAvailabilityRepository $availabilityRepository,
        private ReservationRepository $reservationRepository,
    ) {
    }

    /**
     * Bloque chaque nuit de la plage [from, to] incluse (usage personnel, travaux…).
     */
    public function blockPeriod(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        $this->eachDay($property, $from, $to, static function (PropertyAvailability $day): void {
            $day->setIsAvailable(false);
        });
        $this->em->flush();
    }

    /**
     * Rend chaque nuit de la plage [from, to] de nouveau disponible.
     */
    public function unblockPeriod(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        $this->eachDay($property, $from, $to, static function (PropertyAvailability $day): void {
            $day->setIsAvailable(true);
        });
        $this->em->flush();
    }

    /**
     * Applique un tarif journalier spécifique sur la plage (optionnel, sujet A.1).
     */
    public function setDailyPrice(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to, ?string $price): void
    {
        $this->eachDay($property, $from, $to, static function (PropertyAvailability $day) use ($price): void {
            $day->setPriceOverride($price);
        });
        $this->em->flush();
    }

    /**
     * Définit une durée de séjour minimum sur la plage (optionnel, sujet A.1).
     */
    public function setMinimumStay(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to, ?int $minimumStay): void
    {
        $this->eachDay($property, $from, $to, static function (PropertyAvailability $day) use ($minimumStay): void {
            $day->setMinimumStay($minimumStay);
        });
        $this->em->flush();
    }

    /**
     * Vue mensuelle : grille de jours avec statut (passé, réservé, bloqué, disponible) et tarif.
     *
     * @return array{
     *     year:int, month:int, label:string,
     *     firstWeekday:int,
     *     days:list<array{date:\DateTimeImmutable, day:int, past:bool, status:string, price:string}>
     * }
     */
    public function buildMonthView(Property $property, int $year, int $month): array
    {
        $first = (new \DateTimeImmutable())->setDate($year, $month, 1)->setTime(0, 0, 0);
        $daysInMonth = (int) $first->format('t');
        $last = $first->setDate($year, $month, $daysInMonth);
        $today = (new \DateTimeImmutable())->setTime(0, 0, 0);

        $availability = $this->availabilityRepository->findInRangeIndexedByDate($property, $first, $last);
        $bookedNights = $this->bookedNights($property, $first, $last);
        $basePrice = $property->getPricePerNight() ?? '0';

        $days = [];
        for ($d = 1; $d <= $daysInMonth; ++$d) {
            $date = $first->setDate($year, $month, $d);
            $key = $date->format('Y-m-d');
            $row = $availability[$key] ?? null;

            if (isset($bookedNights[$key])) {
                $status = 'booked';
            } elseif ($row !== null && !$row->isAvailable()) {
                $status = 'blocked';
            } else {
                $status = 'available';
            }

            $days[] = [
                'date' => $date,
                'day' => $d,
                'past' => $date < $today,
                'status' => $status,
                'price' => $row?->getPriceOverride() ?? $basePrice,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'label' => $this->monthLabel($month) . ' ' . $year,
            'firstWeekday' => (int) $first->format('N'),
            'days' => $days,
        ];
    }

    /**
     * Vue mensuelle GLOBALE : agrège réservations confirmées et jours bloqués de tous
     * les logements de l'hôte. Chaque jour porte la liste des événements (logement + statut).
     *
     * @return array{
     *     year:int, month:int, label:string, firstWeekday:int,
     *     days:list<array{date:\DateTimeImmutable, day:int, past:bool,
     *         events:list<array{title:string, status:string}>}>
     * }
     */
    public function buildGlobalMonthView(User $host, int $year, int $month): array
    {
        $first = (new \DateTimeImmutable())->setDate($year, $month, 1)->setTime(0, 0, 0);
        $daysInMonth = (int) $first->format('t');
        $last = $first->setDate($year, $month, $daysInMonth);
        $today = (new \DateTimeImmutable())->setTime(0, 0, 0);

        // date (Y-m-d) => list d'événements
        $eventsByDate = [];

        foreach ($this->reservationRepository->findConfirmedForHostInRange($host, $first, $last->modify('+1 day')) as $reservation) {
            $cursor = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            while ($cursor < $checkout) {
                $eventsByDate[$cursor->format('Y-m-d')][] = [
                    'title' => $reservation->getProperty()->getTitle(),
                    'status' => 'booked',
                ];
                $cursor = $cursor->modify('+1 day');
            }
        }

        foreach ($this->availabilityRepository->findBlockedForHostInRange($host, $first, $last) as $blocked) {
            $eventsByDate[$blocked->getAvailableDate()->format('Y-m-d')][] = [
                'title' => $blocked->getProperty()->getTitle(),
                'status' => 'blocked',
            ];
        }

        $days = [];
        for ($d = 1; $d <= $daysInMonth; ++$d) {
            $date = $first->setDate($year, $month, $d);
            $days[] = [
                'date' => $date,
                'day' => $d,
                'past' => $date < $today,
                'events' => $eventsByDate[$date->format('Y-m-d')] ?? [],
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'label' => $this->monthLabel($month) . ' ' . $year,
            'firstWeekday' => (int) $first->format('N'),
            'days' => $days,
        ];
    }

    /**
     * Nuits occupées par une réservation confirmée sur la plage (chaque nuit [checkin, checkout[).
     *
     * @return array<string, true>
     */
    private function bookedNights(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $reservations = $this->reservationRepository->findOverlappingConfirmed(
            $property,
            $from,
            $to->modify('+1 day'),
        );

        $nights = [];
        foreach ($reservations as $reservation) {
            $cursor = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            while ($cursor < $checkout) {
                $nights[$cursor->format('Y-m-d')] = true;
                $cursor = $cursor->modify('+1 day');
            }
        }

        return $nights;
    }

    /**
     * Applique une mutation sur chaque jour de [from, to], en réutilisant ou créant la ligne.
     */
    private function eachDay(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to, callable $mutate): void
    {
        $from = $from->setTime(0, 0, 0);
        $to = $to->setTime(0, 0, 0);
        if ($to < $from) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure ou égale à la date de début.');
        }

        $existing = $this->availabilityRepository->findInRangeIndexedByDate($property, $from, $to);

        $cursor = $from;
        while ($cursor <= $to) {
            $key = $cursor->format('Y-m-d');
            $day = $existing[$key] ?? null;
            if ($day === null) {
                $day = (new PropertyAvailability())
                    ->setProperty($property)
                    ->setAvailableDate($cursor);
                $this->em->persist($day);
            }
            $mutate($day);
            $cursor = $cursor->modify('+1 day');
        }
    }

    private function monthLabel(int $month): string
    {
        $labels = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        return $labels[$month] ?? (string) $month;
    }
}
