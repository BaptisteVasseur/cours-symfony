<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationConfirmedMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class ReservationConfirmedMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ReservationRepository $reservationRepository,
    ) {}

    public function __invoke(ReservationConfirmedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        if ($guest === null) {
            return;
        }

        $subject = $reservation->getStatus() === 'confirmed'
            ? 'Confirmation de votre réservation'
            : 'Votre demande de réservation est en attente';

        $body = $reservation->getStatus() === 'confirmed'
            ? sprintf(
                '<p>Bonjour,</p><p>Votre réservation du <strong>%s</strong> au <strong>%s</strong> est confirmée. Bon séjour !</p>',
                $reservation->getCheckinDate()?->format('d/m/Y'),
                $reservation->getCheckoutDate()?->format('d/m/Y'),
            )
            : sprintf(
                '<p>Bonjour,</p><p>Votre demande de réservation du <strong>%s</strong> au <strong>%s</strong> a bien été reçue. L\'hôte doit encore l\'accepter.</p>',
                $reservation->getCheckinDate()?->format('d/m/Y'),
                $reservation->getCheckoutDate()?->format('d/m/Y'),
            );

        $guestEmail = (new Email())
            ->from('noreply@staybook.com')
            ->to($guest->getEmail())
            ->subject($subject)
            ->html($body);

        $this->mailer->send($guestEmail);

        if ($reservation->getStatus() === 'confirmed') {
            $host = $reservation->getProperty()?->getHost();
            if ($host !== null && $host->getEmail() !== null) {
                $propertyTitle = $reservation->getProperty()?->getTitle() ?? 'Logement';
                $hostBody = sprintf(
                    '<p>Bonjour,</p>'
                    . '<p>La réservation pour <strong>%s</strong> du <strong>%s</strong> au <strong>%s</strong> est maintenant confirmée.</p>',
                    htmlspecialchars($propertyTitle),
                    $reservation->getCheckinDate()?->format('d/m/Y'),
                    $reservation->getCheckoutDate()?->format('d/m/Y'),
                );

                $hostEmail = (new Email())
                    ->from('noreply@staybook.com')
                    ->to($host->getEmail())
                    ->subject(sprintf('Réservation confirmée pour %s', $propertyTitle))
                    ->html($hostBody);

                $this->mailer->send($hostEmail);
            }
        }
    }
}
