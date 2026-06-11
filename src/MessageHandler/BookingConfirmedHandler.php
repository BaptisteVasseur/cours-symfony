<?php

namespace App\MessageHandler;

use App\Message\BookingConfirmedMessage;
use App\Repository\BookingRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class BookingConfirmedHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepo,
        private readonly MailerInterface   $mailer,
        private readonly Environment       $twig,
    ) {}

    public function __invoke(BookingConfirmedMessage $message): void
    {
        $booking = $this->bookingRepo->find($message->bookingId);
        if ($booking === null) {
            return;
        }

        $guest = $booking->getGuest();
        $host  = $booking->getListing()->getHost();

        $html = $this->twig->render('emails/booking_confirmed.html.twig', ['booking' => $booking]);

        foreach ([$guest->getEmail(), $host->getEmail()] as $recipient) {
            $email = (new Email())
                ->from('no-reply@airbnb-clone.local')
                ->to($recipient)
                ->subject('Réservation confirmée — ' . $booking->getListing()->getTitle())
                ->html($html);

            $this->mailer->send($email);
        }
    }
}
