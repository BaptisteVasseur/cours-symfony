<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Reservation;
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

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@airbnb-clone.local', 'Airbnb Clone'))
            ->to(new Address($guest->getEmail(), $guest->getProfile()?->getFirstName() ?? ''))
            ->subject('Votre séjour est réservé !')
            ->htmlTemplate('emails/booking_confirmation.html.twig')
            ->context([
                'reservation' => $reservation,
                'property' => $property,
                'reservationUrl' => $reservationUrl,
            ]);

        $this->mailer->send($email);
    }
}
