<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Levée lorsqu'une réservation ne peut aboutir car les dates sont devenues
 * indisponibles (jour bloqué ou chevauchement détecté pendant la transaction
 * verrouillée). Le message est destiné à l'utilisateur.
 */
final class BookingConflictException extends \RuntimeException
{
}
