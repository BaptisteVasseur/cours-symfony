<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingCancelledMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class BookingCancelledHandler
{
    public function __construct(private readonly MailerInterface $mailer) {}

    public function __invoke(BookingCancelledMessage $message): void
    {
        $cancellerLabel = match ($message->cancelledBy) {
            'host'  => 'l\'hôte',
            'admin' => 'un administrateur',
            default => 'le voyageur',
        };

        $this->mailer->send(
            (new Email())
                ->from('noreply@airbnb-clone.local')
                ->to($message->guestEmail)
                ->subject('Réservation annulée — ' . $message->propertyTitle)
                ->html($this->guestBody($message, $cancellerLabel))
        );

        $this->mailer->send(
            (new Email())
                ->from('noreply@airbnb-clone.local')
                ->to($message->hostEmail)
                ->subject('Réservation annulée — ' . $message->propertyTitle)
                ->html($this->hostBody($message, $cancellerLabel))
        );
    }

    private function guestBody(BookingCancelledMessage $m, string $cancellerLabel): string
    {
        $reason = $m->cancellationReason
            ? '<p>Motif : ' . htmlspecialchars($m->cancellationReason) . '</p>'
            : '';

        return sprintf(
            '<p>Bonjour %s,</p>
            <p>Votre réservation pour <strong>%s</strong> du <strong>%s</strong> au <strong>%s</strong>
            a été annulée par %s.</p>%s',
            htmlspecialchars($m->guestFirstName),
            htmlspecialchars($m->propertyTitle),
            $m->checkinDate,
            $m->checkoutDate,
            $cancellerLabel,
            $reason,
        );
    }

    private function hostBody(BookingCancelledMessage $m, string $cancellerLabel): string
    {
        $reason = $m->cancellationReason
            ? '<p>Motif : ' . htmlspecialchars($m->cancellationReason) . '</p>'
            : '';

        return sprintf(
            '<p>Bonjour,</p>
            <p>La réservation de <strong>%s</strong> pour <strong>%s</strong>
            du <strong>%s</strong> au <strong>%s</strong> a été annulée par %s.</p>%s',
            htmlspecialchars($m->guestFirstName),
            htmlspecialchars($m->propertyTitle),
            $m->checkinDate,
            $m->checkoutDate,
            $cancellerLabel,
            $reason,
        );
    }
}
