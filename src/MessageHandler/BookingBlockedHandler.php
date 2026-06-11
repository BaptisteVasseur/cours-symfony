<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingBlockedMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class BookingBlockedHandler
{
    public function __construct(private readonly MailerInterface $mailer) {}

    public function __invoke(BookingBlockedMessage $message): void
    {
        $this->mailer->send(
            (new Email())
                ->from('noreply@airbnb-clone.local')
                ->to($message->guestEmail)
                ->subject('Votre demande de réservation a été annulée — ' . $message->propertyTitle)
                ->html(sprintf(
                    '<p>Bonjour %s,</p>
                    <p>Nous vous informons que votre demande de réservation en attente pour <strong>%s</strong>
                    a été annulée car l\'hôte a bloqué la période du <strong>%s</strong> au <strong>%s</strong>.</p>
                    <p>Vous pouvez effectuer une nouvelle recherche pour trouver un autre logement disponible.</p>',
                    htmlspecialchars($message->guestFirstName),
                    htmlspecialchars($message->propertyTitle),
                    $message->blockedFrom,
                    $message->blockedTo,
                ))
        );
    }
}
