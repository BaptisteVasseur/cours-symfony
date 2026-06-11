<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingRejectedMessage;
use App\Repository\BookingRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class BookingRejectedHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(BookingRejectedMessage $message): void
    {
        $booking = $this->bookingRepository->find($message->bookingId);
        if ($booking === null) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@clone-airbnb.local', 'Clone Airbnb'))
            ->to($booking->getGuest()->getEmail())
            ->subject('Demande de réservation refusée — ' . $booking->getListing()->getTitle())
            ->htmlTemplate('emails/booking_rejected.html.twig')
            ->context(['booking' => $booking]);

        $this->mailer->send($email);
    }
}
