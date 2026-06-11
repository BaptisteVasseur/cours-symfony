<?php

declare(strict_types=1);

namespace App\Service\Exception;

/**
 * Thrown when a requested date range cannot be booked. The message is end-user facing (French).
 */
final class UnavailableDatesException extends \RuntimeException
{
}
