<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingExpiredMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class BookingExpiredHandler
{
    public function __construct(private readonly MailerInterface $mailer) {}

    public function __invoke(BookingExpiredMessage $message): void
    {
        $this->mailer->send(
            (new Email())
                ->from('noreply@airbnb-clone.local')
                ->to($message->guestEmail)
                ->subject('Votre demande de réservation a expiré — ' . $message->propertyTitle)
                ->html(sprintf(
                    '<p>Bonjour %s,</p>
                    <p>Votre demande de réservation pour <strong>%s</strong>
                    du <strong>%s</strong> au <strong>%s</strong> a expiré
                    faute de réponse de l\'hôte dans les 48h.</p>
                    <p>Vous pouvez effectuer une nouvelle recherche pour trouver un autre logement.</p>',
                    htmlspecialchars($message->guestFirstName),
                    htmlspecialchars($message->propertyTitle),
                    $message->checkinDate,
                    $message->checkoutDate,
                ))
        );
    }
}
