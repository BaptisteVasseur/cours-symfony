<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Levée lorsqu'une transition de statut de réservation n'est pas autorisée
 * (transition interdite par le cycle de vie, ou motif d'annulation manquant).
 */
final class InvalidReservationTransitionException extends \DomainException
{
}
