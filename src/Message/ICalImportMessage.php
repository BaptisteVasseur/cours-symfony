<?php

declare(strict_types=1);

namespace App\Message;

class ICalImportMessage
{
    public function __construct(public readonly string $iCalSyncId)
    {
    }
}
