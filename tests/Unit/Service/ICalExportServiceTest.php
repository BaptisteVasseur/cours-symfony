<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Booking;
use App\Entity\Listing;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Service\ICalExportService;
use PHPUnit\Framework\TestCase;

final class ICalExportServiceTest extends TestCase
{
    public function testExportProducesRfc5545Calendar(): void
    {
        $guest = (new User())->setFirstName('Jean')->setLastName('Dupont')->setEmail('jean.dupont@email.com');
        $listing = (new Listing())->setTitle('Appartement Centre-Ville');
        $booking = (new Booking())
            ->setListing($listing)
            ->setGuest($guest)
            ->setCheckIn(new \DateTimeImmutable('2026-07-10'))
            ->setCheckOut(new \DateTimeImmutable('2026-07-15'))
            ->setNightsCount(5)
            ->setTotalAmount('450.00')
            ->setCurrency('EUR');

        $repo = $this->createMock(BookingRepository::class);
        $repo->method('findConfirmedForListing')->willReturn([$booking]);

        $ics = (new ICalExportService($repo))->export($listing);

        self::assertStringContainsString("BEGIN:VCALENDAR\r\n", $ics);
        self::assertStringContainsString('VERSION:2.0', $ics);
        self::assertStringContainsString('PRODID:', $ics);
        self::assertStringContainsString('BEGIN:VEVENT', $ics);
        self::assertStringContainsString('UID:res-', $ics);
        self::assertStringContainsString('DTSTART;VALUE=DATE:20260710', $ics);
        // DTEND exclusif = jour de checkout (modèle semi-ouvert).
        self::assertStringContainsString('DTEND;VALUE=DATE:20260715', $ics);
        self::assertStringContainsString('END:VEVENT', $ics);
        self::assertStringContainsString('END:VCALENDAR', $ics);
        // Lignes terminées en CRLF.
        self::assertStringEndsWith("\r\n", $ics);
    }
}
