<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationCreatedMessage;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class ReservationCreatedMessageHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $senderEmail = 'noreply@staynest.local',
    ) {}

    public function __invoke(ReservationCreatedMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()?->getHost();

        $dashboardUrl = $this->urlGenerator->generate(
            'app_host_reservations_index',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        if ($guest !== null) {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->from($this->senderEmail)
                    ->to($guest->getEmail())
                    ->subject('Booking request received — ' . $reservation->getProperty()?->getTitle())
                    ->htmlTemplate('emails/reservation_created.html.twig')
                    ->context([
                        'reservation' => $reservation,
                        'recipient' => $guest,
                        'role' => 'guest',
                        'dashboardUrl' => $dashboardUrl,
                    ])
            );
        }

        if ($host !== null) {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->from($this->senderEmail)
                    ->to($host->getEmail())
                    ->subject('New booking request — ' . $reservation->getProperty()?->getTitle())
                    ->htmlTemplate('emails/reservation_created.html.twig')
                    ->context([
                        'reservation' => $reservation,
                        'recipient' => $host,
                        'role' => 'host',
                        'dashboardUrl' => $dashboardUrl,
                    ])
            );
        }
    }
}
