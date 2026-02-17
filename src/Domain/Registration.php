<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\DTO\UserRegistrationDTO;
use App\Domain\SecondaryPorts\DatabaseServiceInterface;
use App\Domain\SecondaryPorts\MailerServiceInterface;

class Registration
{
    public function __construct(
        protected DatabaseServiceInterface $database,
        protected MailerServiceInterface $mailer,
    ) {}

    public function makeARegistration(UserRegistrationDTO $userRegistrationDTO)
    {
        $user = $this->database->createUserFromDTO($userRegistrationDTO);

        $token = uniqid('', true);
        $user->setRegistrationToken($token);

        $this->database->save($user);

        $this->mailer->sendRegistrationEmail($userRegistrationDTO->email, $token);
    }
}
