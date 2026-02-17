<?php

declare(strict_types=1);

namespace App\Domain\SecondaryPorts;

use App\Domain\DTO\UserRegistrationDTO;

interface DatabaseServiceInterface
{
    public function findByEmail(string $email);

    public function save($user);

    public function createUserFromDTO(UserRegistrationDTO $userRegistrationDTO);
}
