<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\ReservationEmailNotificationMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ReservationEmailNotificationHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationEmailNotificationMessage $message): void
    {
        $reservation = $this->reservationRepository->find(Uuid::fromString($message->reservationId));
        if (!$reservation instanceof Reservation) {
            return;
        }

        foreach ($this->buildEmails($reservation, $message) as $email) {
            $this->mailer->send($email);
        }
    }

    /**
     * @return list<Email>
     */
    private function buildEmails(Reservation $reservation, ReservationEmailNotificationMessage $message): array
    {
        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $host = $property?->getHost();

        if ($property === null || $guest === null) {
            return [];
        }

        $emails = [];
        $summary = sprintf(
            "%s\nDates : %s au %s\nVoyageurs : %d\nTotal : %s %s",
            $property->getTitle(),
            $reservation->getCheckinDate()?->format('d/m/Y') ?? '-',
            $reservation->getCheckoutDate()?->format('d/m/Y') ?? '-',
            $reservation->getGuestsCount() ?? 0,
            $reservation->getTotalPrice(),
            $reservation->getCurrency() ?? 'EUR',
        );

        if ($message->type === 'pending' && $host?->getEmail() !== null) {
            $emails[] = $this->createEmail($host->getEmail(), 'Nouvelle demande de reservation', $summary);
        }

        if ($message->type === 'confirmed') {
            if ($guest->getEmail() !== null) {
                $emails[] = $this->createEmail($guest->getEmail(), 'Reservation confirmee', $summary);
            }
            if ($host?->getEmail() !== null) {
                $emails[] = $this->createEmail($host->getEmail(), 'Reservation confirmee', $summary);
            }
        }

        if (in_array($message->type, ['cancelled', 'declined'], true)) {
            $body = $summary . "\nMotif : " . ($message->reason ?? $reservation->getCancellationReason() ?? '-');
            $subject = $message->type === 'declined' ? 'Demande refusee' : 'Reservation annulee';

            if ($guest->getEmail() !== null) {
                $emails[] = $this->createEmail($guest->getEmail(), $subject, $body);
            }
            if ($host?->getEmail() !== null) {
                $emails[] = $this->createEmail($host->getEmail(), $subject, $body);
            }
        }

        return $emails;
    }

    private function createEmail(string $to, string $subject, string $body): Email
    {
        return (new Email())
            ->from('no-reply@clone-airbnb.local')
            ->to($to)
            ->subject($subject)
            ->text($body);
    }
}
