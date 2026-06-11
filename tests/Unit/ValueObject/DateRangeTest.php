<?php

declare(strict_types=1);

namespace App\Tests\Unit\ValueObject;

use App\ValueObject\DateRange;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    public function testNightsCountsFullDays(): void
    {
        $range = DateRange::fromStrings('2026-07-10', '2026-07-15');

        self::assertSame(5, $range->nights());
    }

    public function testCheckoutMustBeAfterCheckin(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DateRange::fromStrings('2026-07-10', '2026-07-10');
    }

    public function testTouchingRangesDoNotOverlap(): void
    {
        $a = DateRange::fromStrings('2026-07-10', '2026-07-15');
        $b = DateRange::fromStrings('2026-07-15', '2026-07-18');

        self::assertFalse($a->overlaps($b));
        self::assertFalse($b->overlaps($a));
    }

    public function testOverlappingRangesAreDetectedSymmetrically(): void
    {
        $a = DateRange::fromStrings('2026-07-10', '2026-07-15');
        $b = DateRange::fromStrings('2026-07-14', '2026-07-20');

        self::assertTrue($a->overlaps($b));
        self::assertTrue($b->overlaps($a));
    }

    public function testIsInPast(): void
    {
        $now = new \DateTimeImmutable('2026-07-10');
        $past = DateRange::fromStrings('2026-07-01', '2026-07-05');
        $future = DateRange::fromStrings('2026-07-20', '2026-07-25');

        self::assertTrue($past->isInPast($now));
        self::assertFalse($future->isInPast($now));
    }
}
