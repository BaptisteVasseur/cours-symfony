<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ICalImportService;
use PHPUnit\Framework\TestCase;

final class ICalImportServiceTest extends TestCase
{
    public function testParsesVeventWithDateValues(): void
    {
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'BEGIN:VEVENT',
            'UID:abc-123@airbnb',
            'DTSTART;VALUE=DATE:20260710',
            'DTEND;VALUE=DATE:20260715',
            'SUMMARY:Reserved',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        $events = (new ICalImportService())->parse($ics);

        self::assertCount(1, $events);
        self::assertSame('abc-123@airbnb', $events[0]->uid);
        self::assertSame('2026-07-10', $events[0]->start->format('Y-m-d'));
        self::assertSame('2026-07-15', $events[0]->end->format('Y-m-d'));
        self::assertSame('Reserved', $events[0]->summary);
    }

    public function testUnfoldsContinuationLinesAndHandlesDateTime(): void
    {
        // SUMMARY plié sur deux lignes (RFC 5545) + DTSTART au format DATE-TIME.
        $ics = implode("\r\n", [
            'BEGIN:VEVENT',
            'UID:folded-1',
            'DTSTART:20260801T140000Z',
            'DTEND:20260803T110000Z',
            'SUMMARY:Long summary that is',
            '  folded across lines',
            'END:VEVENT',
        ]);

        $events = (new ICalImportService())->parse($ics);

        self::assertCount(1, $events);
        self::assertSame('2026-08-01', $events[0]->start->format('Y-m-d'));
        self::assertSame('2026-08-03', $events[0]->end->format('Y-m-d'));
        self::assertSame('Long summary that is folded across lines', $events[0]->summary);
    }

    public function testEventWithoutDtstartIsIgnored(): void
    {
        $ics = "BEGIN:VEVENT\r\nUID:no-date\r\nEND:VEVENT";

        self::assertCount(0, (new ICalImportService())->parse($ics));
    }
}
