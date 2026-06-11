<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCancelledMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class ReservationCancelledMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ReservationRepository $reservationRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(ReservationCancelledMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);

        if ($reservation === null) {
            return;
        }

        $property = $reservation->getProperty();
        $propertyTitle = $property?->getTitle() ?? 'Logement';
        $checkin = $reservation->getCheckinDate()?->format('d/m/Y');
        $checkout = $reservation->getCheckoutDate()?->format('d/m/Y');
        $cancellationReason = $reservation->getCancellationReason() ?? 'Aucun motif fourni.';

        $guest = $reservation->getGuest();
        if ($guest !== null && $guest->getEmail() !== null) {
            $guestBody = sprintf(
                '<p>Bonjour,</p>'
                . '<p>Votre réservation pour <strong>%s</strong> du <strong>%s</strong> au <strong>%s</strong> a été annulée.</p>'
                . '<p>Motif : %s</p>',
                htmlspecialchars($propertyTitle),
                $checkin,
                $checkout,
                htmlspecialchars($cancellationReason),
            );

            $guestEmail = (new Email())
                ->from('noreply@staybook.com')
                ->to($guest->getEmail())
                ->subject('Votre réservation a été annulée')
                ->html($guestBody);

            $this->mailer->send($guestEmail);
            $this->notificationService->notify($guest, sprintf('Votre réservation pour %s a été annulée.', $propertyTitle));
        }

        $host = $property?->getHost();
        if ($host !== null && $host->getEmail() !== null) {
            $hostBody = sprintf(
                '<p>Bonjour,</p>'
                . '<p>Une réservation pour <strong>%s</strong> du <strong>%s</strong> au <strong>%s</strong> a été annulée.</p>'
                . '<p>Motif : %s</p>',
                htmlspecialchars($propertyTitle),
                $checkin,
                $checkout,
                htmlspecialchars($cancellationReason),
            );

            $hostEmail = (new Email())
                ->from('noreply@staybook.com')
                ->to($host->getEmail())
                ->subject('Une réservation a été annulée')
                ->html($hostBody);

            $this->mailer->send($hostEmail);
            $this->notificationService->notify($host, sprintf('Une réservation pour %s a été annulée.', $propertyTitle), '/compte/demandes');
        }

        $this->em->flush();
    }
}
