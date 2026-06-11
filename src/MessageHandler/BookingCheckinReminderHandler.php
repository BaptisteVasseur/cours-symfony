<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingCheckinReminderMessage;
use App\Repository\BookingRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;


#[AsMessageHandler]
final class BookingCheckinReminderHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(BookingCheckinReminderMessage $message): void
    {
        $booking = $this->bookingRepository->find($message->bookingId);
        if ($booking === null) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@clone-airbnb.local', 'Clone Airbnb'))
            ->to($booking->getGuest()->getEmail())
            ->subject('Votre séjour commence demain — ' . $booking->getListing()->getTitle())
            ->htmlTemplate('emails/booking_checkin_reminder.html.twig')
            ->context([
                'booking' => $booking,
                'location' => $booking->getListing()->getLocation(),
                'host' => $booking->getListing()->getHost(),
            ]);

        $this->mailer->send($email);
    }
}
