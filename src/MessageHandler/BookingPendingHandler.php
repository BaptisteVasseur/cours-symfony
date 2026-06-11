<?php

namespace App\MessageHandler;

use App\Message\BookingPendingMessage;
use App\Repository\BookingRepository;
use App\Service\NotificationService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

#[AsMessageHandler]
final class BookingPendingHandler
{
    public function __construct(
        private readonly BookingRepository    $bookingRepo,
        private readonly MailerInterface      $mailer,
        private readonly Environment          $twig,
        private readonly NotificationService  $notificationService,
        private readonly UrlGeneratorInterface $router,
    ) {}

    public function __invoke(BookingPendingMessage $message): void
    {
        $booking = $this->bookingRepo->find($message->bookingId);
        if ($booking === null) {
            return;
        }

        $host    = $booking->getListing()->getHost();
        $listing = $booking->getListing();

        $html = $this->twig->render('emails/booking_pending.html.twig', ['booking' => $booking]);

        $email = (new Email())
            ->from('no-reply@airbnb-clone.local')
            ->to($host->getEmail())
            ->subject('Nouvelle demande de réservation — ' . $listing->getTitle())
            ->html($html);

        $this->mailer->send($email);

        $this->notificationService->create(
            $host,
            'Nouvelle demande de réservation',
            'Demande reçue pour ' . $listing->getTitle() . ' du ' . $booking->getStartDate()->format('d/m/Y') . ' au ' . $booking->getEndDate()->format('d/m/Y') . '.',
            $this->router->generate('host_bookings', [], UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }
}
