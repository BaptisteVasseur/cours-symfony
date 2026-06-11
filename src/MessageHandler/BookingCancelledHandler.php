<?php

namespace App\MessageHandler;

use App\Message\BookingCancelledMessage;
use App\Repository\BookingRepository;
use App\Service\NotificationService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

#[AsMessageHandler]
final class BookingCancelledHandler
{
    public function __construct(
        private readonly BookingRepository     $bookingRepo,
        private readonly MailerInterface       $mailer,
        private readonly Environment           $twig,
        private readonly NotificationService   $notificationService,
        private readonly UrlGeneratorInterface $router,
    ) {}

    public function __invoke(BookingCancelledMessage $message): void
    {
        $booking = $this->bookingRepo->find($message->bookingId);
        if ($booking === null) {
            return;
        }

        $guest   = $booking->getGuest();
        $host    = $booking->getListing()->getHost();
        $listing = $booking->getListing();

        $html = $this->twig->render('emails/booking_cancelled.html.twig', [
            'booking'     => $booking,
            'cancelledBy' => $message->cancelledBy,
            'reason'      => $message->reason,
        ]);

        foreach ([$guest->getEmail(), $host->getEmail()] as $recipient) {
            $email = (new Email())
                ->from('no-reply@airbnb-clone.local')
                ->to($recipient)
                ->subject('Réservation annulée — ' . $listing->getTitle())
                ->html($html);

            $this->mailer->send($email);
        }

        $bookingUrl = $this->router->generate('booking_show', ['id' => $booking->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->notificationService->create(
            $guest,
            'Réservation annulée',
            'Votre réservation pour ' . $listing->getTitle() . ' a été annulée. Motif : ' . $message->reason,
            $bookingUrl,
        );

        $this->notificationService->create(
            $host,
            'Réservation annulée',
            'La réservation pour ' . $listing->getTitle() . ' a été annulée. Motif : ' . $message->reason,
            $this->router->generate('host_bookings', [], UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }
}
