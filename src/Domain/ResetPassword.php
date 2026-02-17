<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\SecondaryPorts\DatabaseServiceInterface;
use App\Domain\SecondaryPorts\MailerServiceInterface;

class ResetPassword
{
    public function __construct(
        protected DatabaseServiceInterface $database,
        protected MailerServiceInterface $mailer,
    ) {}

    public function makeAPasswordRequest(string $email): void
    {
        $user = $this->database->findByEmail($email);

        if (!$user) {
            throw new \Exception('User not found !');
        }

        $token = uniqid('', true);
        $user->setResetPasswordToken($token);

        $this->database->save($user);

        $this->mailer->sendPasswordRequestEmail($email, $token);
    }
}
