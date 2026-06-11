<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Reservation;
use App\Entity\Message;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function sendNewMessageEmail(Message $message, User $recipient): void
    {
        $emailAddress = $recipient->getEmail();
        if ($emailAddress === null) {
            return;
        }

        $conversationUrl = $this->urlGenerator->generate(
            'app_messages_show',
            ['id' => $message->getConversation()?->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@airbnb-clone.local', 'Airbnb Clone'))
            ->to(new Address($emailAddress, $this->displayName($recipient)))
            ->subject('Nouveau message reçu')
            ->htmlTemplate('emails/new_message.html.twig')
            ->context([
                'messageContent' => $message->getContent(),
                'senderName' => $this->displayName($message->getSender()),
                'conversationUrl' => $conversationUrl,
                'recipient' => $recipient,
            ]);

        $this->mailer->send($email);
    }

    public function sendRegistrationEmail(User $user): void
    {
        $loginUrl = $this->urlGenerator->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@airbnb-clone.local', 'Airbnb Clone'))
            ->to(new Address($user->getEmail(), $user->getProfile()?->getFirstName() ?? ''))
            ->subject('Bienvenue sur Airbnb Clone')
            ->htmlTemplate('emails/registration.html.twig')
            ->context([
                'user' => $user,
                'loginUrl' => $loginUrl,
            ]);

        $this->mailer->send($email);
    }

    public function sendBookingConfirmationEmail(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if ($guest === null) {
            return;
        }

        $property = $reservation->getProperty();
        if ($property === null) {
            return;
        }

        $reservationUrl = $this->urlGenerator->generate('app_reservation_show', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->sendReservationEmail(
            $reservation,
            $guest,
            'Votre séjour est réservé !',
            'emails/booking_confirmation.html.twig',
            ['reservationUrl' => $reservationUrl],
        );
    }

    public function sendBookingPendingHostEmail(Reservation $reservation): void
    {
        $property = $reservation->getProperty();
        $host = $property?->getHost();
        if ($host === null) {
            return;
        }

        $this->sendReservationEmail(
            $reservation,
            $host,
            'Nouvelle demande de réservation',
            'emails/booking_pending_host.html.twig',
            [
                'hostReservationsUrl' => $this->urlGenerator->generate('app_host_reservations', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        );
    }

    public function sendBookingConfirmationEmails(Reservation $reservation): void
    {
        $this->sendBookingConfirmationEmail($reservation);

        $property = $reservation->getProperty();
        $host = $property?->getHost();
        if ($host === null) {
            return;
        }

        $this->sendReservationEmail(
            $reservation,
            $host,
            'Réservation confirmée',
            'emails/booking_confirmed_host.html.twig',
            [
                'reservationUrl' => $this->urlGenerator->generate('app_reservation_show', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        );
    }

    public function sendBookingRefusedEmail(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if ($guest === null) {
            return;
        }

        $this->sendReservationEmail(
            $reservation,
            $guest,
            'Votre demande de réservation a été refusée',
            'emails/booking_refused.html.twig',
            [
                'reservationUrl' => $this->urlGenerator->generate('app_reservation_show', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        );
    }

    public function sendBookingCancelledEmails(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if ($guest !== null) {
            $this->sendReservationEmail(
                $reservation,
                $guest,
                'Réservation annulée',
                'emails/booking_cancelled.html.twig',
                [
                    'reservationUrl' => $this->urlGenerator->generate('app_reservation_show', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
            );
        }

        $host = $reservation->getProperty()?->getHost();
        if ($host !== null && $host->getId() !== $guest?->getId()) {
            $this->sendReservationEmail(
                $reservation,
                $host,
                'Réservation annulée',
                'emails/booking_cancelled.html.twig',
                [
                    'reservationUrl' => $this->urlGenerator->generate('app_reservation_show', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
            );
        }
    }

    public function sendCheckinReminderEmail(Reservation $reservation): void
    {
        $guest = $reservation->getGuest();
        if ($guest === null) {
            return;
        }

        $reservationUrl = $this->urlGenerator->generate('app_reservation_show', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->sendReservationEmail(
            $reservation,
            $guest,
            'Rappel : Votre séjour commence demain !',
            'emails/booking_checkin_reminder.html.twig',
            [
                'reservationUrl' => $reservationUrl,
            ],
        );
    }

    private function sendReservationEmail(
        Reservation $reservation,
        User $recipient,
        string $subject,
        string $template,
        array $context = [],
    ): void {
        $emailAddress = $recipient->getEmail();
        $property = $reservation->getProperty();
        if ($emailAddress === null || $property === null) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@airbnb-clone.local', 'Airbnb Clone'))
            ->to(new Address($emailAddress, $this->displayName($recipient)))
            ->subject($subject)
            ->htmlTemplate($template)
            ->context([
                'reservation' => $reservation,
                'property' => $property,
                'recipient' => $recipient,
                ...$context,
            ]);

        $this->mailer->send($email);
    }

    private function displayName(User $user): string
    {
        $profile = $user->getProfile();
        $name = trim(sprintf('%s %s', $profile?->getFirstName() ?? '', $profile?->getLastName() ?? ''));

        return $name !== '' ? $name : (string) $user->getEmail();
    }
}
