<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class BookingRequest
{
    #[Assert\NotNull(message: 'La date d\'arrivée est obligatoire.')]
    public ?\DateTimeImmutable $checkinDate = null;

    #[Assert\NotNull(message: 'La date de départ est obligatoire.')]
    #[Assert\GreaterThan(propertyPath: 'checkinDate', message: 'La date de départ doit être postérieure à la date d\'arrivée.')]
    public ?\DateTimeImmutable $checkoutDate = null;

    #[Assert\NotNull(message: 'Le nombre de voyageurs est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'Il doit y avoir au moins un voyageur.')]
    public ?int $guestsCount = 1;
}
