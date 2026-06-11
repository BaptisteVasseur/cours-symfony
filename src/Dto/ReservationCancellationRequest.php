<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ReservationCancellationRequest
{
    #[Assert\NotBlank(message: 'Le motif est obligatoire.')]
    #[Assert\Length(max: 2000, maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $reason = null;
}
