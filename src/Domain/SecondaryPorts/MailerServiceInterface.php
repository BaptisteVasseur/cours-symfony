<?php

declare(strict_types=1);

namespace App\Domain\SecondaryPorts;

interface MailerServiceInterface
{
    public function sendPasswordRequestEmail(string $email, string $token);

    public function sendRegistrationEmail(string $email, string $token);
}
