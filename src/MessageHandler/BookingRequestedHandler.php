<?php

namespace App\MessageHandler;

use App\Message\BookingRequestedMessage;
use App\Repository\BookingRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BookingRequestedHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepo,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(BookingRequestedMessage $message): void
    {
        $booking = $this->bookingRepo->find($message->bookingId);
        if (!$booking) {
            return;
        }

        $host = $booking->getProperty()->getHost();

        $email = (new TemplatedEmail())
            ->from('noreply@airbnb-clone.local')
            ->to($host->getEmail())
            ->subject('Nouvelle demande de réservation — ' . $booking->getProperty()->getTitle())
            ->htmlTemplate('email/booking_requested.html.twig')
            ->context(['booking' => $booking]);

        $this->mailer->send($email);
    }
}
