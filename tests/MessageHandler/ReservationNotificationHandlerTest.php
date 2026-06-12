<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Reservation;
use App\Enum\ReservationNotificationType;
use App\Message\ReservationNotification;
use App\MessageHandler\ReservationNotificationHandler;
use App\Tests\Support\ReservationFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

final class ReservationNotificationHandlerTest extends WebTestCase
{
    use ReservationFactoryTrait;

    private EntityManagerInterface $em;
    private ReservationNotificationHandler $handler;
    private \DateTimeImmutable $base;

    protected function setUp(): void
    {
        self::createClient();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->handler = $container->get(ReservationNotificationHandler::class);

        $this->em->getConnection()->beginTransaction();
        $this->base = (new \DateTimeImmutable('today'))->modify('+10 days');
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testNouvelleDemandeNotifieHoteAvecVoyageurMontantEtCta(): void
    {
        $reservation = $this->reservation('pending');
        $host = $reservation->getProperty()->getHost();
        $guest = $reservation->getGuest();

        ($this->handler)(new ReservationNotification((string) $reservation->getId(), ReservationNotificationType::CREATED_PENDING));

        self::assertEmailCount(2);
        $hostEmail = $this->emailTo((string) $host->getEmail());
        self::assertNotNull($hostEmail);
        $html = (string) $hostEmail->getHtmlBody();
        self::assertStringContainsString((string) $guest->getEmail(), $html); // infos voyageur
        self::assertStringContainsString('100,00', $html);                    // montant
        self::assertStringContainsString('/compte/demandes', $html);          // CTA d'action
    }

    public function testReservationValideeNotifieVoyageurEtHote(): void
    {
        $reservation = $this->reservation('confirmed');
        $guest = $reservation->getGuest();
        $host = $reservation->getProperty()->getHost();

        ($this->handler)(new ReservationNotification((string) $reservation->getId(), ReservationNotificationType::ACCEPTED));

        self::assertEmailCount(2);
        self::assertNotNull($this->emailTo((string) $guest->getEmail()));
        self::assertNotNull($this->emailTo((string) $host->getEmail()));
    }

    public function testRefusContientLeMotif(): void
    {
        $reservation = $this->reservation('cancelled', 'Logement indisponible à ces dates');

        ($this->handler)(new ReservationNotification((string) $reservation->getId(), ReservationNotificationType::REFUSED));

        self::assertEmailCount(1);
        self::assertStringContainsString('Logement indisponible à ces dates', (string) $this->getMailerMessage(0)->getHtmlBody());
    }

    public function testAnnulationContientLeMotif(): void
    {
        $reservation = $this->reservation('cancelled', 'Imprévu de dernière minute');

        ($this->handler)(new ReservationNotification((string) $reservation->getId(), ReservationNotificationType::CANCELLED));

        self::assertEmailCount(2);
        self::assertStringContainsString('Imprévu de dernière minute', (string) $this->getMailerMessage(0)->getHtmlBody());
    }

    private function reservation(string $status, ?string $reason = null): Reservation
    {
        $property = $this->makePublishedProperty($this->em, city: 'Lyon', title: 'Logement notif ' . uniqid('', true));
        $guest = $this->makeUser($this->em, 'guest-' . uniqid('', true) . '@test.local');
        $reservation = $this->makeReservation($this->em, $property, $guest, $this->base, $this->base->modify('+5 days'), $status);

        if ($reason !== null) {
            $reservation->setCancellationReason($reason);
            $this->em->flush();
        }

        return $reservation;
    }

    private function emailTo(string $address): ?Email
    {
        foreach ($this->getMailerMessages() as $message) {
            if (!$message instanceof Email) {
                continue;
            }
            foreach ($message->getTo() as $recipient) {
                if ($recipient->getAddress() === $address) {
                    return $message;
                }
            }
        }

        return null;
    }
}
