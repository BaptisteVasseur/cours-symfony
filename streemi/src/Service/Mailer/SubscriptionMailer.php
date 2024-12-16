<?php

declare(strict_types=1);

namespace App\Service\Mailer;

use App\Entity\Subscription;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class SubscriptionMailer
{
    public function __construct(
        protected Environment $twig,
        protected MailerInterface $mailer,
    )
    {
    }

    public function sendNewSubscription(User $user, Subscription $subscription): void
    {
        $email = (new Email())
            ->from('contact@streemi.fr')
            ->to($user->getEmail())
            ->subject('Merci pour votre achat !')
            ->html($this->twig->render('emails/new_subscriptions.html.twig', [
                'user' => $user,
                'subscription' => $subscription
            ]));

        $this->mailer->send($email);
    }

}
