<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Message\ReservationNotificationMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[AsMessageHandler]
final readonly class ReservationNotificationMessageHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private RouterInterface $router,
    ) {
    }

    public function __invoke(ReservationNotificationMessage $message): void
    {
        $reservation = $this->reservationRepository->find($message->reservationId);
        if ($reservation === null || $reservation->getProperty() === null || $reservation->getGuest() === null) {
            return;
        }

        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $host = $property->getHost();
        $recipients = match ($message->event) {
            'pending' => $host !== null ? [$host] : [],
            'confirmed', 'cancelled' => array_values(array_filter([$guest, $host])),
            default => [],
        };

        $propertyTitle = $property->getTitle() ?? '';

        $guestProfile = $guest->getProfile();
        $guestName = $guestProfile !== null && $guestProfile->getFirstName() !== null
            ? trim($guestProfile->getFirstName() . ' ' . ($guestProfile->getLastName() ?? ''))
            : ($guest->getEmail() ?? 'Voyageur');

        $hostProfile = $host?->getProfile();
        $hostName = $host !== null && $hostProfile !== null && $hostProfile->getFirstName() !== null
            ? trim($hostProfile->getFirstName() . ' ' . ($hostProfile->getLastName() ?? ''))
            : ($host?->getEmail() ?? 'Hôte');

        $reservationUrl = $this->router->generate(
            'app_reservation_show',
            ['id' => $reservation->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $propertyUrl = $this->router->generate(
            'app_logement_detail',
            ['id' => $property->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $propertyAddress = $property->getAddress();

        foreach ($recipients as $recipient) {
            $notification = (new Notification())
                ->setUser($recipient)
                ->setType('reservation')
                ->setTitle($this->titleFor($message->event))
                ->setContent(sprintf('%s du %s au %s.', $propertyTitle, $reservation->getCheckinDate()?->format('d/m/Y'), $reservation->getCheckoutDate()?->format('d/m/Y')))
                ->setChannel('email');

            $this->entityManager->persist($notification);

            if ($recipient->getEmail() !== null) {
                $isHost = $host !== null && $recipient->getId()?->equals($host->getId()) === true;

                $email = (new TemplatedEmail())
                    ->from('no-reply@clone-airbnb.local')
                    ->to($recipient->getEmail())
                    ->subject($this->subjectFor($message->event, $isHost, $propertyTitle))
                    ->htmlTemplate('emails/reservation_notification.html.twig')
                    ->context([
                        'reservation' => $reservation,
                        'event' => $message->event,
                        'recipient' => $recipient,
                        'isHost' => $isHost,
                        'isGuest' => !$isHost && $recipient->getId()?->equals($guest->getId()) === true,
                        'reservationUrl' => $reservationUrl,
                        'propertyUrl' => $propertyUrl,
                        'guestName' => $guestName,
                        'guestEmail' => $guest->getEmail() ?? '',
                        'hostName' => $hostName,
                        'hostEmail' => $host?->getEmail() ?? '',
                        'hostPhone' => $host?->getPhone(),
                        'propertyAddress' => $propertyAddress,
                    ]);

                $this->mailer->send($email);
            }
        }

        $this->entityManager->flush();
    }

    private function subjectFor(string $event, bool $isHost, string $propertyTitle): string
    {
        return match ($event) {
            'pending' => 'Nouvelle demande de réservation - ' . $propertyTitle,
            'confirmed' => $isHost
                ? 'Réservation confirmée - ' . $propertyTitle
                : 'Votre réservation est confirmée - ' . $propertyTitle,
            'cancelled' => 'Réservation annulée - ' . $propertyTitle,
            default => 'Mise à jour de réservation - ' . $propertyTitle,
        };
    }

    private function titleFor(string $event): string
    {
        return match ($event) {
            'pending' => 'Nouvelle demande de réservation',
            'confirmed' => 'Réservation confirmée',
            'cancelled' => 'Réservation annulée',
            default => 'Mise à jour de réservation',
        };
    }
}
