<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\ReservationNotificationType;

/**
 * Job asynchrone : notification email liée à une réservation.
 * On transporte l'identifiant (pas l'entité) car le message est sérialisé en file.
 */
final readonly class ReservationNotification
{
    public function __construct(
        public string $reservationId,
        public ReservationNotificationType $type,
    ) {
    }
}
