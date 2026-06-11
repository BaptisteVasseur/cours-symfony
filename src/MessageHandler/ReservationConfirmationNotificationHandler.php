<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationConfirmationNotification;
use App\Repository\ReservationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

/**
 * Envoie l'email de notification de réservation.
 * Exécuté de façon asynchrone par le worker Messenger (transport "async").
 */
#[AsMessageHandler]
final class ReservationConfirmationNotificationHandler
{
    private const string FROM_ADDRESS = 'noreply@airbnb-clone.fr';
    private const string FROM_NAME = 'Airbnb Clone';

    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ReservationConfirmationNotification $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            // La réservation a pu être supprimée entre l'envoi et le traitement du message.
            $this->logger->warning('Réservation introuvable pour la notification email.', [
                'reservationId' => $message->reservationId,
            ]);

            return;
        }

        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();
        $status = $reservation->getStatus();

        $subject = match ($status) {
            'confirmed' => 'Votre réservation est confirmée 🎉',
            'cancelled' => 'Réservation annulée',
            default => 'Votre demande de réservation a bien été reçue',
        };

        // Destinataires : le voyageur par défaut ; une ANNULATION notifie les DEUX parties.
        $recipients = [];
        if ($guest?->getEmail() !== null) {
            $recipients['guest'] = $guest->getEmail();
        }
        if ($status === 'cancelled' && $host?->getEmail() !== null) {
            $recipients['host'] = $host->getEmail();
        }

        foreach ($recipients as $role => $address) {
            $email = (new TemplatedEmail())
                ->from(new Address(self::FROM_ADDRESS, self::FROM_NAME))
                ->to(new Address($address))
                ->subject($subject)
                ->htmlTemplate('emails/reservation_confirmation.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'property' => $reservation->getProperty(),
                    'guest' => $guest,
                    'status' => $status,
                    'recipientRole' => $role,
                ]);

            $this->mailer->send($email);
        }

        $this->logger->info('Notification de réservation envoyée.', [
            'reservationId' => $message->reservationId,
            'status' => $status,
            'recipients' => array_values($recipients),
        ]);
    }
}
