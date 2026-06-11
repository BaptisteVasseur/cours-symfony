<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingConfirmedMessage;
use App\Repository\BookingRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class BookingConfirmedHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(BookingConfirmedMessage $message): void
    {
        $booking = $this->bookingRepository->find($message->bookingId);
        if ($booking === null) {
            return;
        }

        $from = new Address('no-reply@clone-airbnb.local', 'Clone Airbnb');
        $subject = 'Réservation confirmée — ' . $booking->getListing()->getTitle();

        foreach ([$booking->getGuest(), $booking->getListing()->getHost()] as $recipient) {
            $email = (new TemplatedEmail())
                ->from($from)
                ->to($recipient->getEmail())
                ->subject($subject)
                ->htmlTemplate('emails/booking_confirmed.html.twig')
                ->context([
                    'booking' => $booking,
                    'recipient' => $recipient,
                    'isHost' => $recipient === $booking->getListing()->getHost(),
                ]);

            $this->mailer->send($email);
        }
    }
}
