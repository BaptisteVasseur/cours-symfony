<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyBlockedPeriod;
use App\Entity\Reservation;
use App\Repository\PropertyBlockedPeriodRepository;
use App\Repository\ReservationRepository;

/**
 * Construit les données du calendrier hôte (vue mensuelle et hebdomadaire)
 * et applique les règles de blocage des créneaux :
 *  - une réservation "confirmed" bloque toujours ses dates ;
 *  - une réservation "pending" bloque les dates si le logement est en mode
 *    "sur demande" (instantBooking = false) ;
 *  - sur un logement en réservation instantanée, une "pending" ne bloque le
 *    créneau que pendant les 15 minutes suivant sa création (verrou de
 *    paiement), après quoi elle est ignorée.
 *
 * Les indisponibilités déclarées par l'hôte sont des périodes bornées à la
 * minute près (PropertyBlockedPeriod), ce qui permet une rotation type
 * « du 12 juin 17:00 au 13 juin 11:00 ».
 */
final class HostCalendar
{
    public const PENDING_LOCK_MINUTES = 15;

    private const DEFAULT_CHECKIN_HOUR = 15;
    private const DEFAULT_CHECKOUT_HOUR = 11;

    public function __construct(
        private readonly PropertyBlockedPeriodRepository $blockedPeriodRepository,
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    public function isBlocking(Reservation $reservation): bool
    {
        $status = $reservation->getStatus();
        if ($status === 'confirmed') {
            return true;
        }
        if ($status !== 'pending') {
            return false;
        }

        if (!$reservation->getProperty()->isInstantBooking()) {
            return true;
        }

        $lockExpiresAt = $reservation->getCreatedAt()?->modify(sprintf('+%d minutes', self::PENDING_LOCK_MINUTES));

        return $lockExpiresAt !== null && $lockExpiresAt > new \DateTimeImmutable();
    }

    /**
     * Grille mensuelle : semaines de 7 jours, chaque jour portant un statut
     * (free | blocked | pending | reserved) et un libellé.
     *
     * @return array{
     *     month: \DateTimeImmutable,
     *     prev: \DateTimeImmutable,
     *     next: \DateTimeImmutable,
     *     weeks: list<list<array{date: \DateTimeImmutable, inMonth: bool, isPast: bool, status: string, label: ?string}>>,
     * }
     */
    public function buildMonth(Property $property, \DateTimeImmutable $anchor): array
    {
        $firstOfMonth = $anchor->modify('first day of this month')->setTime(0, 0);
        $gridStart = $this->startOfWeek($firstOfMonth);
        $lastOfMonth = $firstOfMonth->modify('last day of this month');
        $gridEnd = $this->startOfWeek($lastOfMonth)->modify('+6 days');
        $gridEndExclusive = $gridEnd->modify('+1 day');

        $periods = $this->blockedPeriodRepository->findOverlapping($property, $gridStart, $gridEndExclusive);
        $reservations = $this->reservationRepository->findOverlappingForProperty($property, $gridStart, $gridEnd);

        $today = new \DateTimeImmutable('today');
        $weeks = [];
        $week = [];
        for ($day = $gridStart; $day <= $gridEnd; $day = $day->modify('+1 day')) {
            $status = $this->dayStatus($property, $day, $periods, $reservations);
            $week[] = [
                'date' => $day,
                'inMonth' => $day->format('Y-m') === $firstOfMonth->format('Y-m'),
                'isPast' => $day < $today,
                'status' => $status['status'],
                'label' => $status['label'],
            ];
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return [
            'month' => $firstOfMonth,
            'prev' => $firstOfMonth->modify('-1 month'),
            'next' => $firstOfMonth->modify('+1 month'),
            'weeks' => $weeks,
        ];
    }

    /**
     * Vue hebdomadaire à l'heure près : pour chaque jour, des segments
     * horaires occupés (réservations bornées par les heures d'arrivée/départ
     * du logement, indisponibilités hôte bornées par leurs heures réelles).
     *
     * @return array{
     *     weekStart: \DateTimeImmutable,
     *     weekEnd: \DateTimeImmutable,
     *     prev: \DateTimeImmutable,
     *     next: \DateTimeImmutable,
     *     days: list<array{date: \DateTimeImmutable, isPast: bool, segments: list<array{startHour: float, endHour: float, type: string, label: string}>}>,
     * }
     */
    public function buildWeek(Property $property, \DateTimeImmutable $anchor): array
    {
        $weekStart = $this->startOfWeek($anchor);
        $weekEnd = $weekStart->modify('+6 days');
        $weekEndExclusive = $weekStart->modify('+7 days');

        $periods = $this->blockedPeriodRepository->findOverlapping($property, $weekStart, $weekEndExclusive);
        $reservations = array_filter(
            $this->reservationRepository->findOverlappingForProperty($property, $weekStart, $weekEnd),
            fn (Reservation $r): bool => $this->isBlocking($r),
        );

        $today = new \DateTimeImmutable('today');
        $days = [];
        for ($day = $weekStart; $day <= $weekEnd; $day = $day->modify('+1 day')) {
            $dayStart = $day;
            $dayEnd = $day->modify('+1 day');
            $segments = [];

            foreach ($periods as $period) {
                $segment = $this->clampToDay($period->getStartAt(), $period->getEndAt(), $dayStart, $dayEnd);
                if ($segment !== null) {
                    $segments[] = [
                        'startHour' => $segment[0],
                        'endHour' => $segment[1],
                        'type' => 'blocked',
                        'label' => $period->getReason() ?? 'Indisponible',
                    ];
                }
            }

            foreach ($reservations as $reservation) {
                $segment = $this->reservationSegmentForDay($property, $reservation, $day);
                if ($segment !== null) {
                    $segments[] = $segment;
                }
            }

            $days[] = [
                'date' => $day,
                'isPast' => $day < $today,
                'segments' => $segments,
            ];
        }

        return [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'prev' => $weekStart->modify('-7 days'),
            'next' => $weekStart->modify('+7 days'),
            'days' => $days,
        ];
    }

    /**
     * Périodes d'indisponibilité encore en cours ou à venir, pour la liste
     * latérale (avec leur identifiant pour suppression).
     *
     * @return list<PropertyBlockedPeriod>
     */
    public function upcomingBlockedPeriods(Property $property): array
    {
        return $this->blockedPeriodRepository->findUpcoming($property, new \DateTimeImmutable());
    }

    /**
     * Réservations bloquantes (nuitées) qui chevauchent la période [start, end].
     * Le jour de départ libère sa nuit : une réservation dont le checkout vaut
     * le jour de début du blocage n'entre pas en conflit.
     *
     * @return list<Reservation>
     */
    public function findBlockingReservations(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $startDate = $start->setTime(0, 0);
        $endDate = $end->setTime(0, 0);

        $overlapping = $this->reservationRepository->findOverlappingForProperty($property, $startDate, $endDate);

        return array_values(array_filter(
            $overlapping,
            fn (Reservation $r): bool => $this->isBlocking($r)
                && $r->getCheckoutDate() > $startDate
                && $r->getCheckinDate() < $endDate,
        ));
    }

    /**
     * Statut d'un jour : les nuits réservées priment sur les indisponibilités
     * hôte, qui priment sur "libre".
     *
     * @param list<PropertyBlockedPeriod> $periods
     * @param list<Reservation>           $reservations
     *
     * @return array{status: string, label: ?string}
     */
    private function dayStatus(Property $property, \DateTimeImmutable $day, array $periods, array $reservations): array
    {
        $dayStart = $day;
        $dayEnd = $day->modify('+1 day');

        // Une réservation occupe les nuits [checkin, checkout) ; la nuit du
        // jour J est occupée si checkin <= J et checkout > J.
        foreach ($reservations as $reservation) {
            if (!$this->isBlocking($reservation)) {
                continue;
            }
            if ($reservation->getCheckinDate() <= $day && $reservation->getCheckoutDate() > $day) {
                $status = $reservation->getStatus() === 'confirmed' ? 'reserved' : 'pending';
                $label = $status === 'reserved' ? 'Réservé' : 'En attente';
                $guestName = $this->guestName($reservation);
                if ($guestName !== null) {
                    $label .= ' — ' . $guestName;
                }

                return ['status' => $status, 'label' => $label];
            }
        }

        foreach ($periods as $period) {
            if ($period->getStartAt() < $dayEnd && $period->getEndAt() > $dayStart) {
                return ['status' => 'blocked', 'label' => $period->getReason() ?? 'Indisponible'];
            }
        }

        return ['status' => 'free', 'label' => null];
    }

    /**
     * Portion horaire d'une réservation sur un jour donné : du checkin (15h
     * par défaut) au checkout (11h par défaut), journée pleine entre les deux.
     *
     * @return array{startHour: float, endHour: float, type: string, label: string}|null
     */
    private function reservationSegmentForDay(Property $property, Reservation $reservation, \DateTimeImmutable $day): ?array
    {
        $checkinHour = $this->timeToHour($property->getCheckinTime(), self::DEFAULT_CHECKIN_HOUR);
        $checkoutHour = $this->timeToHour($property->getCheckoutTime(), self::DEFAULT_CHECKOUT_HOUR);

        $dayKey = $day->format('Y-m-d');
        $checkinKey = $reservation->getCheckinDate()->format('Y-m-d');
        $checkoutKey = $reservation->getCheckoutDate()->format('Y-m-d');

        if ($dayKey < $checkinKey || $dayKey > $checkoutKey) {
            return null;
        }

        $startHour = $dayKey === $checkinKey ? $checkinHour : 0.0;
        $endHour = $dayKey === $checkoutKey ? $checkoutHour : 24.0;
        if ($startHour >= $endHour) {
            return null;
        }

        $type = $reservation->getStatus() === 'confirmed' ? 'reserved' : 'pending';
        $label = $type === 'reserved' ? 'Réservé' : 'En attente';
        $guestName = $this->guestName($reservation);
        if ($guestName !== null) {
            $label .= ' — ' . $guestName;
        }

        return [
            'startHour' => $startHour,
            'endHour' => $endHour,
            'type' => $type,
            'label' => $label,
        ];
    }

    /**
     * Intersection d'un intervalle [start, end] avec une journée [dayStart,
     * dayEnd), exprimée en heures décimales [0, 24].
     *
     * @return array{0: float, 1: float}|null
     */
    private function clampToDay(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        \DateTimeImmutable $dayStart,
        \DateTimeImmutable $dayEnd,
    ): ?array {
        $from = $start > $dayStart ? $start : $dayStart;
        $to = $end < $dayEnd ? $end : $dayEnd;
        if ($from >= $to) {
            return null;
        }

        $startHour = (float) $from->format('G') + (int) $from->format('i') / 60;
        $endHour = $to->format('Y-m-d') !== $dayStart->format('Y-m-d')
            ? 24.0
            : (float) $to->format('G') + (int) $to->format('i') / 60;

        return [$startHour, $endHour];
    }

    private function timeToHour(?\DateTimeImmutable $time, int $default): float
    {
        if ($time === null) {
            return (float) $default;
        }

        return (float) $time->format('G') + (int) $time->format('i') / 60;
    }

    private function guestName(Reservation $reservation): ?string
    {
        $profile = $reservation->getGuest()?->getProfile();
        if ($profile === null) {
            return null;
        }

        $name = trim(sprintf('%s %s', $profile->getFirstName() ?? '', $profile->getLastName() ?? ''));

        return $name !== '' ? $name : null;
    }

    private function startOfWeek(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $date = $date->setTime(0, 0);

        return $date->format('N') === '1' ? $date : $date->modify('last monday');
    }
}
