<?php

declare(strict_types=1);

namespace App\Service\Availability\Exception;

use App\Service\Availability\AvailabilityResult;

final class PropertyNotAvailableException extends \RuntimeException
{
    public function __construct(public readonly AvailabilityResult $result)
    {
        parent::__construct($result->getReason()?->message() ?? 'Logement indisponible.');
    }
}
