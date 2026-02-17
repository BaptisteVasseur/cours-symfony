<?php

declare(strict_types=1);

namespace App\Domain\DTO;

readonly class UserRegistrationDTO
{
    public function __construct(
        public string $email,
        public string $firstName,
        public string $lastName,
    )
    {
    }
}
