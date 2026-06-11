<?php

declare(strict_types=1);

namespace App\Service\Availability;

use App\Entity\Property;
use App\Entity\Reservation;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final class AvailabilityChecker
{
    private const CONFIRMED_STATUS = 'confirmed';
    private const PUBLISHED_STATUS = 'published';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function check(
        Property $property,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        ?Reservation $excludeReservation = null,
    ): AvailabilityResult {
        if ($guests < 1 || $checkin >= $checkout) {
            return AvailabilityResult::ko(UnavailabilityReason::INVALID_DATES);
        }

        $propertyId = $property->getId();
        if (!$propertyId instanceof Uuid) {
            return AvailabilityResult::ko(UnavailabilityReason::PROPERTY_NOT_FOUND);
        }

        $excludeId = $excludeReservation?->getId();
        $excludeIdString = $excludeId instanceof Uuid ? $excludeId->toRfc4122() : null;

        $sql = <<<'SQL'
            SELECT
                (p.status = :published_status) AS is_published,
                (p.max_guests >= :guests) AS capacity_ok,
                EXISTS (
                    SELECT 1
                    FROM unavailabilities u
                    WHERE u.property_id = p.id
                      AND u.start_date < :checkout
                      AND u.end_date > :checkin
                ) AS has_unavailability,
                EXISTS (
                    SELECT 1
                    FROM reservations r
                    WHERE r.property_id = p.id
                      AND r.status = :confirmed_status
                      AND r.checkin_date < :checkout
                      AND r.checkout_date > :checkin
                      AND (:exclude_id IS NULL OR r.id <> :exclude_id)
                ) AS has_reservation
            FROM properties p
            WHERE p.id = :property_id
        SQL;

        $row = $this->connection->fetchAssociative($sql, [
            'property_id' => $propertyId->toRfc4122(),
            'published_status' => self::PUBLISHED_STATUS,
            'confirmed_status' => self::CONFIRMED_STATUS,
            'guests' => $guests,
            'checkin' => $checkin->format('Y-m-d'),
            'checkout' => $checkout->format('Y-m-d'),
            'exclude_id' => $excludeIdString,
        ]);

        if ($row === false) {
            return AvailabilityResult::ko(UnavailabilityReason::PROPERTY_NOT_FOUND);
        }

        if (!$this->asBool($row['is_published'])) {
            return AvailabilityResult::ko(UnavailabilityReason::PROPERTY_NOT_PUBLISHED);
        }

        if (!$this->asBool($row['capacity_ok'])) {
            return AvailabilityResult::ko(UnavailabilityReason::CAPACITY_EXCEEDED);
        }

        if ($this->asBool($row['has_unavailability'])) {
            return AvailabilityResult::ko(UnavailabilityReason::BLOCKED_BY_HOST);
        }

        if ($this->asBool($row['has_reservation'])) {
            return AvailabilityResult::ko(UnavailabilityReason::ALREADY_BOOKED);
        }

        return AvailabilityResult::ok();
    }

    private function asBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['t', 'true', '1'], true);
        }

        return (bool) $value;
    }
}
