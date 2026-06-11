<?php

namespace App\DataFixtures;

/**
 * Faker provider renvoyant des \DateTimeImmutable.
 * Doctrine refuse les \DateTime mutables pour les colonnes *_immutable.
 */
final class ImmutableDateProvider
{
    public function immutableDateTimeBetween(string $startDate = '-2 years', string $endDate = 'now'): \DateTimeImmutable
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        $ts = random_int(min($start, $end), max($start, $end));

        return (new \DateTimeImmutable())->setTimestamp($ts);
    }

    public function immutableDateBetween(string $startDate = '-2 years', string $endDate = 'now'): \DateTimeImmutable
    {
        return $this->immutableDateTimeBetween($startDate, $endDate)->setTime(0, 0);
    }

    public function immutableTime(int $hour = 8, int $maxHour = 20): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->setTime(random_int($hour, $maxHour), 0);
    }

    /**
     * Date fixe et déterministe (ex: "2026-07-10", "+10 days").
     */
    public function immutableDate(string $date): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($date))->setTime(0, 0);
    }
}
