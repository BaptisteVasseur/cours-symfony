<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\ReservationNotificationType;

/**
 * Carries the reservation id (not the entity) so the message stays serializable for the async transport.
 */
final readonly class ReservationNotification
{
    public function __construct(
        public string $reservationId,
        public ReservationNotificationType $type,
    ) {
    }
}
