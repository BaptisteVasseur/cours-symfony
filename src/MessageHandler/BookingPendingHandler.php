<?php

namespace App\MessageHandler;

use App\Message\BookingPendingMessage;
use App\Repository\BookingRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class BookingPendingHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepo,
        private readonly MailerInterface   $mailer,
        private readonly Environment       $twig,
    ) {}

    public function __invoke(BookingPendingMessage $message): void
    {
        $booking = $this->bookingRepo->find($message->bookingId);
        if ($booking === null) {
            return;
        }

        $host = $booking->getListing()->getHost();

        $html = $this->twig->render('emails/booking_pending.html.twig', ['booking' => $booking]);

        $email = (new Email())
            ->from('no-reply@airbnb-clone.local')
            ->to($host->getEmail())
            ->subject('Nouvelle demande de réservation — ' . $booking->getListing()->getTitle())
            ->html($html);

        $this->mailer->send($email);
    }
}
