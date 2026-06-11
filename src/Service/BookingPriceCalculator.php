<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;

final class BookingPriceCalculator
{
    public function calculateBreakdown(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): array
    {
        $nights = (int) $checkin->diff($checkout)->days;
        $subtotalCents = $this->moneyToCents($property->getPricePerNight()) * $nights;
        $cleaningFeeCents = $this->moneyToCents($property->getCleaningFee());
        $serviceFeeCents = intdiv(($subtotalCents * 12) + 50, 100);
        $totalCents = $subtotalCents + $cleaningFeeCents + $serviceFeeCents;

        return [
            'nights' => $nights,
            'subtotal' => $this->formatCents($subtotalCents),
            'cleaningFee' => $this->formatCents($cleaningFeeCents),
            'serviceFee' => $this->formatCents($serviceFeeCents),
            'totalPrice' => $this->formatCents($totalCents),
        ];
    }

    public function calculate(Property $property, \DateTimeImmutable $checkin, \DateTimeImmutable $checkout): string
    {
        return $this->calculateBreakdown($property, $checkin, $checkout)['totalPrice'];
    }

    private function moneyToCents(?string $amount): int
    {
        $normalized = str_replace(',', '.', trim($amount ?? '0'));
        if ($normalized === '') {
            return 0;
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$major, $minor] = array_pad(explode('.', $normalized, 2), 2, '0');
        $major = preg_replace('/\D/', '', $major) ?: '0';
        $minor = substr(str_pad(preg_replace('/\D/', '', $minor) ?: '0', 2, '0'), 0, 2);
        $cents = ((int) $major * 100) + (int) $minor;

        return $negative ? -$cents : $cents;
    }

    private function formatCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }
}
