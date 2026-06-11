<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingRequestedMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class BookingRequestedHandler
{
    public function __construct(private readonly MailerInterface $mailer) {}

    public function __invoke(BookingRequestedMessage $message): void
    {
        // Notify guest: request sent
        $this->mailer->send(
            (new Email())
                ->from('noreply@airbnb-clone.local')
                ->to($message->guestEmail)
                ->subject('Votre demande de réservation a bien été envoyée')
                ->html($this->guestBody($message))
        );

        // Notify host: new booking request
        $this->mailer->send(
            (new Email())
                ->from('noreply@airbnb-clone.local')
                ->to($message->hostEmail)
                ->subject('Nouvelle demande de réservation — ' . $message->propertyTitle)
                ->html($this->hostBody($message))
        );
    }

    private function guestBody(BookingRequestedMessage $m): string
    {
        return sprintf(
            '<p>Bonjour %s,</p>
            <p>Votre demande de réservation pour <strong>%s</strong> du <strong>%s</strong> au <strong>%s</strong>
            (%d voyageur(s)) a bien été envoyée.</p>
            <p>Montant total estimé : <strong>%.2f %s</strong></p>
            <p>L\'hôte dispose de 48h pour accepter ou refuser. Vous serez notifié par email.</p>',
            htmlspecialchars($m->guestFirstName),
            htmlspecialchars($m->propertyTitle),
            $m->checkinDate,
            $m->checkoutDate,
            $m->guestsCount,
            $m->totalPrice,
            $m->currency,
        );
    }

    private function hostBody(BookingRequestedMessage $m): string
    {
        return sprintf(
            '<p>Bonjour,</p>
            <p><strong>%s</strong> souhaite réserver <strong>%s</strong>
            du <strong>%s</strong> au <strong>%s</strong> (%d voyageur(s)).</p>
            <p>Montant : <strong>%.2f %s</strong></p>
            <p><a href="%s">Accepter ou refuser la demande</a></p>
            <p>Vous avez 48h pour répondre, passé ce délai la demande expirera automatiquement.</p>',
            htmlspecialchars($m->guestFirstName),
            htmlspecialchars($m->propertyTitle),
            $m->checkinDate,
            $m->checkoutDate,
            $m->guestsCount,
            $m->totalPrice,
            $m->currency,
            $m->hostDashboardUrl,
        );
    }
}
