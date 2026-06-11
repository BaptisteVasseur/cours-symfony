<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingCreatedMessage;
use App\Repository\BookingRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class BookingCreatedHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(BookingCreatedMessage $message): void
    {
        $booking = $this->bookingRepository->find($message->bookingId);
        if ($booking === null) {
            return;
        }

        $host = $booking->getListing()->getHost();

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@clone-airbnb.local', 'Clone Airbnb'))
            ->to($host->getEmail())
            ->subject('Nouvelle demande de réservation — ' . $booking->getListing()->getTitle())
            ->htmlTemplate('emails/booking_created.html.twig')
            ->context(['booking' => $booking]);

        $this->mailer->send($email);
    }
}
