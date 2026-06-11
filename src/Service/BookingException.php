<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Erreur métier du moteur de réservation (dates indisponibles, transition d'état interdite…).
 */
final class BookingException extends \RuntimeException
{
}
