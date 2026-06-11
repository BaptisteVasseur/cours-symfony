<?php

namespace App\MessageHandler;

use App\Message\BookingConfirmedMessage;
use App\Repository\BookingRepository;
use App\Service\NotificationService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

#[AsMessageHandler]
final class BookingConfirmedHandler
{
    public function __construct(
        private readonly BookingRepository     $bookingRepo,
        private readonly MailerInterface       $mailer,
        private readonly Environment           $twig,
        private readonly NotificationService   $notificationService,
        private readonly UrlGeneratorInterface $router,
    ) {}

    public function __invoke(BookingConfirmedMessage $message): void
    {
        $booking = $this->bookingRepo->find($message->bookingId);
        if ($booking === null) {
            return;
        }

        $guest   = $booking->getGuest();
        $host    = $booking->getListing()->getHost();
        $listing = $booking->getListing();

        $html = $this->twig->render('emails/booking_confirmed.html.twig', ['booking' => $booking]);

        foreach ([$guest->getEmail(), $host->getEmail()] as $recipient) {
            $email = (new Email())
                ->from('no-reply@airbnb-clone.local')
                ->to($recipient)
                ->subject('Réservation confirmée — ' . $listing->getTitle())
                ->html($html);

            $this->mailer->send($email);
        }

        $bookingUrl = $this->router->generate('booking_show', ['id' => $booking->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->notificationService->create(
            $guest,
            'Réservation confirmée',
            'Votre réservation pour ' . $listing->getTitle() . ' du ' . $booking->getStartDate()->format('d/m/Y') . ' au ' . $booking->getEndDate()->format('d/m/Y') . ' est confirmée.',
            $bookingUrl,
        );

        $this->notificationService->create(
            $host,
            'Réservation confirmée',
            'La réservation de ' . ($guest->getProfile() ? $guest->getProfile()->getFirstName() : $guest->getEmail()) . ' pour ' . $listing->getTitle() . ' a été confirmée.',
            $this->router->generate('host_bookings', [], UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }
}
