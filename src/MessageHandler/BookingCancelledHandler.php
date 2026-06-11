<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingCancelledMessage;
use App\Repository\BookingRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class BookingCancelledHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(BookingCancelledMessage $message): void
    {
        $booking = $this->bookingRepository->find($message->bookingId);
        if ($booking === null) {
            return;
        }

        $from = new Address('no-reply@clone-airbnb.local', 'Clone Airbnb');
        $subject = 'Réservation annulée — ' . $booking->getListing()->getTitle();

        foreach ([$booking->getGuest(), $booking->getListing()->getHost()] as $recipient) {
            $email = (new TemplatedEmail())
                ->from($from)
                ->to($recipient->getEmail())
                ->subject($subject)
                ->htmlTemplate('emails/booking_cancelled.html.twig')
                ->context([
                    'booking' => $booking,
                    'recipient' => $recipient,
                ]);

            $this->mailer->send($email);
        }
    }
}
