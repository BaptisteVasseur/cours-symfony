<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message asynchrone déclenché à la création d'une réservation.
 * Ne transporte que l'identifiant : le handler recharge la réservation
 * depuis la base pour construire l'email (évite de sérialiser une entité Doctrine).
 */
final class ReservationConfirmationNotification
{
    public function __construct(
        public readonly string $reservationId,
    ) {
    }
}
