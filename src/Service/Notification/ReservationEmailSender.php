<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ReservationEmailSender
{
    private const FROM = 'no-reply@airbnb-clone.local';

    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function sendReservationCreated(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host = $property?->getHost();

        if ($guest instanceof User) {
            $subject = $reservation->getStatus() === 'confirmed'
                ? 'Votre réservation est confirmée'
                : 'Votre demande de réservation a été envoyée';

            $intro = $reservation->getStatus() === 'confirmed'
                ? 'Votre réservation est confirmée.'
                : 'Votre demande a été envoyée à l’hôte. Vous recevrez un e-mail lorsqu’elle sera acceptée ou refusée.';

            $this->sendToUser($guest, $subject, $intro, $reservation, $this->guestReservationUrl($reservation));
        }

        if ($host instanceof User) {
            $intro = $reservation->getStatus() === 'confirmed'
                ? 'Une réservation instantanée vient d’être confirmée sur votre logement.'
                : 'Une nouvelle demande de réservation attend votre réponse.';

            $this->sendToUser($host, 'Nouvelle réservation sur votre logement', $intro, $reservation, $this->hostReservationsUrl());
        }
    }

    public function sendReservationAccepted(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if (!$guest instanceof User) {
            return;
        }

        $this->sendToUser(
            $guest,
            'Votre réservation est acceptée',
            'Bonne nouvelle, l’hôte a accepté votre demande de réservation.',
            $reservation,
            $this->guestReservationUrl($reservation),
        );
    }

    public function sendReservationRejected(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if (!$guest instanceof User) {
            return;
        }

        $reason = trim((string) $reservation->getCancellationReason());
        $intro = 'L’hôte a refusé votre demande de réservation.';
        if ($reason !== '') {
            $intro .= ' Motif : '.$reason;
        }

        $this->sendToUser(
            $guest,
            'Votre demande de réservation est refusée',
            $intro,
            $reservation,
            $this->guestReservationUrl($reservation),
        );
    }

    public function sendReservationReminder(Reservation $reservation): bool
    {
        $guest = $reservation->getGuest();
        if (!$guest instanceof User) {
            return false;
        }

        return $this->sendToUser(
            $guest,
            'Rappel : votre séjour commence demain',
            'Petit rappel : votre réservation commence demain.',
            $reservation,
            $this->guestReservationUrl($reservation),
        );
    }

    private function sendToUser(User $user, string $subject, string $intro, Reservation $reservation, string $url): bool
    {
        $email = $user->getEmail();
        if ($email === null || $email === '') {
            return false;
        }

        $property = $reservation->getProperty();
        $message = (new Email())
            ->from(self::FROM)
            ->to($email)
            ->subject($subject)
            ->html($this->renderHtml($subject, $intro, $reservation, $property, $url))
            ->text($this->renderText($subject, $intro, $reservation, $property, $url));

        try {
            $this->mailer->send($message);

            return true;
        } catch (TransportExceptionInterface $exception) {
            $this->logger->warning('Reservation email could not be sent.', [
                'reservation' => $reservation->getId(),
                'recipient' => $email,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function renderHtml(string $title, string $intro, Reservation $reservation, ?Property $property, string $url): string
    {
        $details = $this->reservationDetails($reservation, $property);

        return sprintf(
            '<h1>%s</h1><p>%s</p><ul><li>Logement : %s</li><li>Arrivée : %s</li><li>Départ : %s</li><li>Voyageurs : %s</li><li>Total : %s %s</li></ul><p><a href="%s">Voir la réservation</a></p>',
            htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($intro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($details['property'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($details['checkin'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($details['checkout'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($details['guests'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($details['total'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($details['currency'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }

    private function renderText(string $title, string $intro, Reservation $reservation, ?Property $property, string $url): string
    {
        $details = $this->reservationDetails($reservation, $property);

        return implode("\n", [
            $title,
            '',
            $intro,
            '',
            'Logement : '.$details['property'],
            'Arrivée : '.$details['checkin'],
            'Départ : '.$details['checkout'],
            'Voyageurs : '.$details['guests'],
            'Total : '.$details['total'].' '.$details['currency'],
            '',
            'Voir la réservation : '.$url,
        ]);
    }

    /**
     * @return array{property: string, checkin: string, checkout: string, guests: string, total: string, currency: string}
     */
    private function reservationDetails(Reservation $reservation, ?Property $property): array
    {
        return [
            'property' => $property?->getTitle() ?? 'Logement',
            'checkin' => $reservation->getCheckinDate()?->format('d/m/Y') ?? '-',
            'checkout' => $reservation->getCheckoutDate()?->format('d/m/Y') ?? '-',
            'guests' => (string) ($reservation->getGuestsCount() ?? 0),
            'total' => number_format((float) $reservation->getTotalPrice(), 2, ',', ' '),
            'currency' => $reservation->getCurrency() ?? 'EUR',
        ];
    }

    private function guestReservationUrl(Reservation $reservation): string
    {
        return $this->urlGenerator->generate('app_reservation_show', [
            'id' => $reservation->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function hostReservationsUrl(): string
    {
        return $this->urlGenerator->generate('app_host_reservation_index', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
