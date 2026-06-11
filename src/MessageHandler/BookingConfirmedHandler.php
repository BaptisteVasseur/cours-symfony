<?php

namespace App\MessageHandler;

use App\Message\BookingConfirmedMessage;
use App\Repository\BookingRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BookingConfirmedHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepo,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(BookingConfirmedMessage $message): void
    {
        $booking = $this->bookingRepo->find($message->bookingId);
        if (!$booking) {
            return;
        }

        $traveler = $booking->getTraveler();
        $host = $booking->getProperty()->getHost();
        $subject = 'Réservation confirmée — ' . $booking->getProperty()->getTitle();
        $template = 'email/booking_confirmed.html.twig';
        $context = ['booking' => $booking];

        foreach ([$traveler->getEmail(), $host->getEmail()] as $recipient) {
            $email = (new TemplatedEmail())
                ->from('noreply@airbnb-clone.local')
                ->to($recipient)
                ->subject($subject)
                ->htmlTemplate($template)
                ->context($context);

            $this->mailer->send($email);
        }
    }
}
