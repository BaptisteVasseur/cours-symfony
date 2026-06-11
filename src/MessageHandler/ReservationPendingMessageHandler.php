<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationPendingMessage;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class ReservationPendingMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ReservationRepository $reservationRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(ReservationPendingMessage $message): void
    {
        $reservation = $this->reservationRepository->findOneForDetail(
            $this->reservationRepository->find($message->reservationId) ?? throw new \InvalidArgumentException('Reservation not found.')
        );

        if ($reservation === null) {
            return;
        }

        $property = $reservation->getProperty();
        $host = $property?->getHost();

        if ($host === null || $host->getEmail() === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $guestProfile = $guest?->getProfile();
        $guestName = $guestProfile !== null
            ? trim(($guestProfile->getFirstName() ?? '') . ' ' . ($guestProfile->getLastName() ?? ''))
            : ($guest?->getEmail() ?? 'Inconnu');

        $propertyTitle = $property->getTitle() ?? 'Logement';

        $body = sprintf(
            '<p>Bonjour,</p>'
            . '<p>Vous avez reçu une nouvelle demande de réservation pour <strong>%s</strong>.</p>'
            . '<ul>'
            . '<li>Voyageur : %s</li>'
            . '<li>Arrivée : %s</li>'
            . '<li>Départ : %s</li>'
            . '<li>Nombre de voyageurs : %d</li>'
            . '<li>Prix total : %s €</li>'
            . '</ul>'
            . '<p><a href="/compte/demandes">Voir les demandes</a></p>',
            htmlspecialchars($propertyTitle),
            htmlspecialchars($guestName),
            $reservation->getCheckinDate()?->format('d/m/Y'),
            $reservation->getCheckoutDate()?->format('d/m/Y'),
            $reservation->getGuestsCount(),
            number_format((float) $reservation->getTotalPrice(), 2, ',', ' '),
        );

        $email = (new Email())
            ->from('noreply@staybook.com')
            ->to($host->getEmail())
            ->subject(sprintf('Nouvelle demande pour %s', $propertyTitle))
            ->html($body);

        $this->mailer->send($email);

        $this->notificationService->notify($host, sprintf('Nouvelle demande de réservation pour %s', $propertyTitle), '/compte/demandes');

        $guest = $reservation->getGuest();
        if ($guest !== null) {
            $this->notificationService->notify($guest, sprintf('Votre demande pour %s a été envoyée, en attente de confirmation.', $propertyTitle), '/reservations/' . $reservation->getId());
        }

        $this->em->flush();
    }
}
