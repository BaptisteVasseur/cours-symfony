<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BookingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $router
    ) {}

    public function accept(Reservation $reservation): void
    {
        $reservation->setStatus('confirmed');
        $this->entityManager->flush();

        $this->sendNotification($reservation, 'confirmée');
        $this->createInAppNotification($reservation->getGuest(), 'Réservation confirmée', 'Votre séjour pour ' . $reservation->getProperty()->getTitle() . ' a été accepté !');
    }

    public function reject(Reservation $reservation, string $reason): void
    {
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $this->entityManager->flush();

        $this->sendNotification($reservation, 'refusée', $reason);
        $this->createInAppNotification($reservation->getGuest(), 'Réservation refusée', 'Désolé, votre demande pour ' . $reservation->getProperty()->getTitle() . ' a été refusée.');
    }

    public function cancel(Reservation $reservation, string $reason): void
    {
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $this->entityManager->flush();

        $this->sendNotification($reservation, 'annulée', $reason);
    }

    public function generateIcal(Property $property): string
    {
        $reservations = $property->getReservations()->filter(fn(Reservation $r) => $r->getStatus() === 'confirmed');

        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Clone Airbnb//FR\r\n";
        foreach ($reservations as $res) {
            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= "UID:res-" . $res->getId() . "@clone-airbnb.local\r\n";
            $ical .= "SUMMARY:Séjour " . $property->getTitle() . " — " . $res->getGuest()?->getProfile()?->getFirstName() . "\r\n";
            $ical .= "DTSTART;VALUE=DATE:" . $res->getCheckinDate()?->format('Ymd') . "\r\n";
            $ical .= "DTEND;VALUE=DATE:" . $res->getCheckoutDate()?->format('Ymd') . "\r\n";
            $ical .= "DESCRIPTION:Séjour " . $res->getCheckinDate()?->diff($res->getCheckoutDate())->days . " nuits — " . $res->getTotalPrice() . "€\r\n";
            $ical .= "END:VEVENT\r\n";
        }
        $ical .= "END:VCALENDAR\r\n";

        return $ical;
    }

    private function sendNotification(Reservation $reservation, string $statusLabel, ?string $reason = null): void
    {
        $email = (new TemplatedEmail())
            ->from('noreply@airbnb-clone.com')
            ->to($reservation->getGuest()?->getEmail() ?? 'test@example.com')
            ->subject('Mise à jour : Votre réservation est ' . $statusLabel)
            ->htmlTemplate('emails/reservation_update.html.twig')
            ->context([
                'reservation' => $reservation,
                'statusLabel' => $statusLabel,
                'reason' => $reason,
                'property' => $reservation->getProperty(),
                'guest' => $reservation->getGuest()
            ]);

        $this->mailer->send($email);
    }

    private function createInAppNotification(?User $user, string $title, string $content): void
    {
        if (!$user) return;

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setContent($content);
        $notification->setType('reservation');
        $notification->setChannel('in-app');
        $notification->setIsRead(false);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }
}
