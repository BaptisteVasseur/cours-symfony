<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingConfirmedMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class BookingConfirmedHandler
{
    public function __construct(private readonly MailerInterface $mailer) {}

    public function __invoke(BookingConfirmedMessage $message): void
    {
        // Notify guest: booking confirmed
        $this->mailer->send(
            (new Email())
                ->from('noreply@airbnb-clone.local')
                ->to($message->guestEmail)
                ->subject('Réservation confirmée — ' . $message->propertyTitle)
                ->html($this->guestBody($message))
        );

        // Notify host: booking confirmed
        $this->mailer->send(
            (new Email())
                ->from('noreply@airbnb-clone.local')
                ->to($message->hostEmail)
                ->subject('Vous avez confirmé une réservation — ' . $message->propertyTitle)
                ->html($this->hostBody($message))
        );
    }

    private function guestBody(BookingConfirmedMessage $m): string
    {
        return sprintf(
            '<p>Bonjour %s,</p>
            <p>Bonne nouvelle ! Votre réservation pour <strong>%s</strong>
            du <strong>%s</strong> au <strong>%s</strong> est confirmée.</p>
            <p>Montant total : <strong>%.2f %s</strong></p>
            <p><a href="%s">Voir ma réservation</a></p>',
            htmlspecialchars($m->guestFirstName),
            htmlspecialchars($m->propertyTitle),
            $m->checkinDate,
            $m->checkoutDate,
            $m->totalPrice,
            $m->currency,
            $m->bookingUrl,
        );
    }

    private function hostBody(BookingConfirmedMessage $m): string
    {
        return sprintf(
            '<p>Bonjour %s,</p>
            <p>Vous avez confirmé la réservation de <strong>%s</strong> pour <strong>%s</strong>
            du <strong>%s</strong> au <strong>%s</strong>.</p>
            <p>Montant : <strong>%.2f %s</strong></p>',
            htmlspecialchars($m->hostFirstName),
            htmlspecialchars($m->guestFirstName),
            htmlspecialchars($m->propertyTitle),
            $m->checkinDate,
            $m->checkoutDate,
            $m->totalPrice,
            $m->currency,
        );
    }
}
