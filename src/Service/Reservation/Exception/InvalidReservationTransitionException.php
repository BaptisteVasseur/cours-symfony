<?php

declare(strict_types=1);

namespace App\Service\Reservation\Exception;

final class InvalidReservationTransitionException extends \RuntimeException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct(sprintf('Transition de réservation interdite : %s → %s.', $from, $to));
    }
}
