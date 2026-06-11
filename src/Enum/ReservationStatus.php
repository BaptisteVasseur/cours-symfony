<?php

declare(strict_types=1);

namespace App\Enum;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Confirmed => 'Confirmée',
            self::Completed => 'Terminée',
            self::Cancelled => 'Annulée',
        };
    }

    /**
     * Statuses that lock the calendar (only confirmed stays block dates — pending does not).
     *
     * @return list<string>
     */
    public static function blocking(): array
    {
        return [self::Confirmed->value];
    }
}
