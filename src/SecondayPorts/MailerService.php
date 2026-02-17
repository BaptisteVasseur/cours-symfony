<?php

declare(strict_types=1);

namespace App\SecondayPorts;

use App\Domain\SecondaryPorts\MailerServiceInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\RouterInterface;

class MailerService implements MailerServiceInterface
{
    public function __construct(
        protected MailerInterface $symfonyMailer,
        protected RouterInterface $symfonyRouter,
    ) {}

    public function sendPasswordRequestEmail(string $email, string $token)
    {
        $this->sendTemplatedEmail(
            $email,
            'Demande de réinitialisation du mot de passe',
            'emails/reset.html.twig',
            $this->symfonyRouter->generate('app_resetpassword_change', ['token' => $token]),
        );
    }

    public function sendRegistrationEmail(string $email, string $token)
    {
        $this->sendTemplatedEmail(
            $email,
            'Confirmation de votre inscription',
            'emails/registration.html.twig',
            $this->symfonyRouter->generate('register', ['token' => $token]),
        );
    }

    protected function sendTemplatedEmail($email, $subject, $template, $url)
    {
        $mail = new TemplatedEmail();

        $mail->to($email);
        $mail->from('contact@example.com');
        $mail->subject($subject);
        $mail->htmlTemplate($template);
        $mail->context([
            'email' => $email,
            'url' => $url,
        ]);

        $this->symfonyMailer->send($mail);
    }
}
