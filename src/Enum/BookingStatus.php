<?php

declare(strict_types=1);

namespace App\Enum;

enum BookingStatus: string
{
    case PENDING   = 'pending';
    case CONFIRMED = 'confirmed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case EXPIRED   = 'expired';

    public function label(): string
    {
        return match($this) {
            self::PENDING   => 'En attente',
            self::CONFIRMED => 'Confirmée',
            self::COMPLETED => 'Terminée',
            self::CANCELLED => 'Annulée',
            self::EXPIRED   => 'Expirée',
        };
    }

    public function isFinal(): bool
    {
        return match($this) {
            self::COMPLETED, self::CANCELLED, self::EXPIRED => true,
            default => false,
        };
    }
}
