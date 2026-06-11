<?php

declare(strict_types=1);

namespace App\Enum;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Confirmed => 'Confirmée',
            self::Cancelled => 'Annulée',
            self::Completed => 'Terminée',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-700',
            self::Confirmed => 'bg-green-100 text-green-700',
            self::Cancelled => 'bg-red-100 text-red-700',
            self::Completed => 'bg-blue-100 text-blue-700',
        };
    }

    public function blocksCalendar(): bool
    {
        return $this === self::Confirmed;
    }
}
