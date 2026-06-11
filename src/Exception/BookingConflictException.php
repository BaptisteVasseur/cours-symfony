<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpFoundation\Response;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
final class BookingConflictException extends \RuntimeException
{
}
