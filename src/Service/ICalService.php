<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ICalService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function export(Property $property): string
    {
        $reservations = $this->reservationRepository->findConfirmedForProperty($property);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($reservations as $reservation) {
            $guest = $reservation->getGuest();
            $guestName = '';
            if ($guest !== null) {
                $profile = $guest->getProfile();
                $guestName = $profile
                    ? trim(($profile->getFirstName() ?? '').' '.($profile->getLastName() ?? ''))
                    : $guest->getEmail();
            }

            $nights = (int) $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;
            $uid = 'res-'.$reservation->getId().'@clone-airbnb.local';
            $summary = $property->getTitle().' — '.$guestName;
            $dtstart = $reservation->getCheckinDate()->format('Ymd');
            $dtend = $reservation->getCheckoutDate()->format('Ymd');
            $description = \sprintf(
                'Séjour %d nuit%s — %s€ — %s',
                $nights,
                $nights > 1 ? 's' : '',
                number_format((float) $reservation->getTotalPrice(), 2, '.', ''),
                $guest?->getEmail() ?? '',
            );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$uid;
            $lines[] = 'SUMMARY:'.$this->escapeIcal($summary);
            $lines[] = 'DTSTART;VALUE=DATE:'.$dtstart;
            $lines[] = 'DTEND;VALUE=DATE:'.$dtend;
            $lines[] = 'DESCRIPTION:'.$this->escapeIcal($description);
            $lines[] = 'DTSTAMP:'.(new \DateTimeImmutable())->format('Ymd\THis\Z');
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * @return list<array{summary: string, dtstart: \DateTimeImmutable, dtend: \DateTimeImmutable}>
     */
    public function import(string $url): array
    {
        $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
        $content = $response->getContent();

        return $this->parseIcal($content);
    }

    /**
     * @return list<array{summary: string, dtstart: \DateTimeImmutable, dtend: \DateTimeImmutable}>
     */
    public function parseIcal(string $content): array
    {
        $events = [];
        $inEvent = false;
        $current = [];

        foreach (explode("\n", str_replace("\r\n", "\n", $content)) as $line) {
            $line = rtrim($line);
            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $current = [];
            } elseif ($line === 'END:VEVENT') {
                $inEvent = false;
                if (isset($current['DTSTART'], $current['DTEND'])) {
                    $dtstart = $this->parseIcalDate($current['DTSTART']);
                    $dtend = $this->parseIcalDate($current['DTEND']);
                    if ($dtstart !== null && $dtend !== null) {
                        $events[] = [
                            'summary' => $current['SUMMARY'] ?? '',
                            'dtstart' => $dtstart,
                            'dtend' => $dtend,
                        ];
                    }
                }
            } elseif ($inEvent && str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $key = explode(';', $key)[0];
                $current[$key] = $value;
            }
        }

        return $events;
    }

    private function parseIcalDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if (preg_match('/^\d{8}$/', $value)) {
            return \DateTimeImmutable::createFromFormat('Ymd', $value) ?: null;
        }
        if (preg_match('/^\d{8}T\d{6}Z?$/', $value)) {
            $fmt = strlen($value) === 16 ? 'Ymd\THis\Z' : 'Ymd\THis';

            return \DateTimeImmutable::createFromFormat($fmt, $value) ?: null;
        }

        return null;
    }

    private function escapeIcal(string $value): string
    {
        return str_replace(['\\', ',', ';', "\n"], ['\\\\', '\\,', '\\;', '\\n'], $value);
    }
}
