<?php

declare(strict_types=1);

namespace App\Exception;

final class UnavailableDatesException extends \RuntimeException
{
    public function __construct(
        public readonly \DateTimeImmutable $checkin,
        public readonly \DateTimeImmutable $checkout,
        string $message = 'Ces dates ne sont pas disponibles pour ce logement.',
    ) {
        parent::__construct($message);
    }
}
