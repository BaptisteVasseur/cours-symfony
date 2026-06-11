<?php

namespace App\Dto;

final readonly class AdminStatsDto
{
    public function __construct(
        public int $totalBookings,
        public int $pendingBookings,
        public int $confirmedBookings,
        public float $totalRevenue,
        public int $totalUsers,
        public int $totalProperties,
    ) {}
}
