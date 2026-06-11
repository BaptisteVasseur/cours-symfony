<?php

namespace App\Message;

final class ReservationEmailMessage
{
    public const NOUVELLE_DEMANDE = 'nouvelle_demande';
    public const RESERVATION_INSTANTANEE = 'reservation_instantanee';
    public const RESERVATION_ACCEPTEE = 'reservation_acceptee';
    public const RESERVATION_REFUSEE = 'reservation_refusee';
    public const RESERVATION_PAYEE = 'reservation_payee';
    public const ANNULATION_VOYAGEUR = 'annulation_voyageur';
    public const ANNULATION_HOTE = 'annulation_hote';

    public function __construct(
        public readonly int $reservationId,
        public readonly string $type,
    ) {
    }
}
