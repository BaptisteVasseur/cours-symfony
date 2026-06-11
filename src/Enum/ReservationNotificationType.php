<?php

declare(strict_types=1);

namespace App\Enum;

enum ReservationNotificationType: string
{
    case RequestReceived = 'request_received';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function subject(): string
    {
        return match ($this) {
            self::RequestReceived => 'Nouvelle demande de réservation',
            self::Confirmed => 'Votre réservation est confirmée',
            self::Cancelled => 'Réservation annulée',
            self::Rejected => 'Votre demande de réservation a été refusée',
        };
    }

    public function template(): string
    {
        return match ($this) {
            self::RequestReceived => 'emails/reservation_request.html.twig',
            self::Confirmed => 'emails/reservation_confirmed.html.twig',
            self::Cancelled, self::Rejected => 'emails/reservation_cancelled.html.twig',
        };
    }

    /**
     * Recipients of the email: 'host', 'guest', or both, per the spec notification table.
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        return match ($this) {
            self::RequestReceived => ['host'],
            self::Confirmed, self::Cancelled => ['guest', 'host'],
            self::Rejected => ['guest'],
        };
    }
}
