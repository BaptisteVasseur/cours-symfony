<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message asynchrone : déclenche l'envoi des emails liés à une réservation.
 * On transporte l'identifiant (pas l'entité) : le handler recharge depuis la base.
 */
final readonly class ReservationNotification
{
    public const EVENT_NEW_REQUEST = 'new_request';
    public const EVENT_CONFIRMED = 'confirmed';
    public const EVENT_REFUSED = 'refused';
    public const EVENT_CANCELLED = 'cancelled';

    public function __construct(
        public string $reservationId,
        public string $event,
    ) {
    }
}
