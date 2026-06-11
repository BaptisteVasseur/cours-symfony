<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message asynchrone : déclenche l'envoi des e-mails transactionnels liés à
 * une réservation. On ne transporte que l'identifiant (et non l'entité) pour
 * rester sérialisable et relire l'état frais au moment de la consommation.
 */
final readonly class ReservationNotification
{
    public const EVENT_REQUESTED = 'requested';              // nouvelle demande -> hôte
    public const EVENT_CONFIRMED = 'confirmed';              // validée -> voyageur + hôte
    public const EVENT_REFUSED = 'refused';                  // refus -> voyageur
    public const EVENT_CANCELLED = 'cancelled';              // annulation -> les deux parties
    public const EVENT_CHECKIN_REMINDER = 'checkin_reminder'; // rappel J-1 -> voyageur

    public function __construct(
        public string $reservationId,
        public string $event,
    ) {
    }
}
