<?php

declare(strict_types=1);

namespace App\Exception;

final class UnavailableDatesException extends \RuntimeException
{
    public function __construct(string $message = 'Ces dates ne sont plus disponibles pour ce logement.')
    {
        parent::__construct($message);
    }
}
