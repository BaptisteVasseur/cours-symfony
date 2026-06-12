<?php

declare(strict_types=1);

namespace App\Tests\Service\Calendar;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Service\Calendar\IcalExporter;
use PHPUnit\Framework\TestCase;

final class IcalExporterTest extends TestCase
{
    private IcalExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new IcalExporter();
    }

    public function testEnveloppeEtFinsDeLigneCrlf(): void
    {
        $ics = $this->exporter->build(new Property(), []);

        self::assertSame(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Clone Airbnb//FR\r\nEND:VCALENDAR\r\n",
            $ics,
        );
    }

    public function testEvenementCompletAvecNomDuVoyageur(): void
    {
        $reservation = $this->makeReservation(
            title: 'Appartement Centre-Ville',
            firstName: 'Jean',
            lastName: 'Dupont',
            email: 'jean.dupont@email.com',
            checkin: '2026-07-10',
            checkout: '2026-07-15',
            totalPrice: '450.00',
        );

        $ics = $this->exporter->build($reservation->getProperty(), [$reservation]);

        self::assertStringContainsString('BEGIN:VEVENT', $ics);
        self::assertStringContainsString('SUMMARY:Appartement Centre-Ville — Jean Dupont', $ics);
        self::assertStringContainsString('DTSTART;VALUE=DATE:20260710', $ics);
        self::assertStringContainsString('DTEND;VALUE=DATE:20260715', $ics);
        self::assertStringContainsString('DESCRIPTION:Séjour 5 nuits — 450€ — jean.dupont@email.com', $ics);
        self::assertStringContainsString('@clone-airbnb.local', $ics);
    }

    public function testEchappementDesCaracteresSpeciaux(): void
    {
        $reservation = $this->makeReservation(
            title: 'Loft; cosy, vue \ jardin',
            firstName: 'Anne',
            lastName: 'Martin',
            email: 'anne@email.com',
            checkin: '2026-07-10',
            checkout: '2026-07-11',
            totalPrice: '80.00',
        );

        $ics = $this->exporter->build($reservation->getProperty(), [$reservation]);

        self::assertStringContainsString('SUMMARY:Loft\; cosy\, vue \\\\ jardin — Anne Martin', $ics);
    }

    public function testNuitSingulierEtFallbackEmailSansProfil(): void
    {
        $reservation = $this->makeReservation(
            title: 'Studio',
            firstName: null,
            lastName: null,
            email: 'sans.profil@email.com',
            checkin: '2026-07-10',
            checkout: '2026-07-11',
            totalPrice: '80.50',
        );

        $ics = $this->exporter->build($reservation->getProperty(), [$reservation]);

        self::assertStringContainsString('SUMMARY:Studio — sans.profil@email.com', $ics);
        self::assertStringContainsString('DESCRIPTION:Séjour 1 nuit — 80.5€ — sans.profil@email.com', $ics);
    }

    public function testDeviseDollar(): void
    {
        $reservation = $this->makeReservation(
            title: 'Villa',
            firstName: 'John',
            lastName: 'Doe',
            email: 'john@email.com',
            checkin: '2026-07-10',
            checkout: '2026-07-12',
            totalPrice: '300.00',
            currency: 'USD',
        );

        $ics = $this->exporter->build($reservation->getProperty(), [$reservation]);

        self::assertStringContainsString('300$', $ics);
    }

    private function makeReservation(
        string $title,
        ?string $firstName,
        ?string $lastName,
        string $email,
        string $checkin,
        string $checkout,
        string $totalPrice,
        string $currency = 'EUR',
    ): Reservation {
        $guest = (new User())->setEmail($email);
        if ($firstName !== null || $lastName !== null) {
            $guest->setProfile((new UserProfile())->setFirstName($firstName)->setLastName($lastName));
        }

        $property = (new Property())->setTitle($title);

        return (new Reservation())
            ->setProperty($property)
            ->setGuest($guest)
            ->setCheckinDate(new \DateTimeImmutable($checkin))
            ->setCheckoutDate(new \DateTimeImmutable($checkout))
            ->setGuestsCount(2)
            ->setStatus('confirmed')
            ->setTotalPrice($totalPrice)
            ->setCurrency($currency);
    }
}
