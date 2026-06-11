<?php

declare(strict_types=1);

namespace App\Service\Exception;

/**
 * Thrown when a reservation status transition is not allowed (e.g. cancelling a completed stay).
 * The message is end-user facing (French).
 */
final class InvalidReservationTransitionException extends \RuntimeException
{
}
