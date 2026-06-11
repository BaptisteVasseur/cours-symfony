<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Import iCal (Partie F) : récupère un flux distant et bloque les nuitées correspondantes.
 *
 * Stratégie de gestion des conflits
 * ---------------------------------
 * - Idempotence : chaque évènement distant est rapproché par (flux, UID). Une nouvelle
 *   synchronisation met à jour les blocages existants au lieu de les dupliquer.
 * - Suppression d'évènements distants : un blocage importé par CE flux dont l'UID n'est
 *   plus présent dans le flux est supprimé (les dates sont de nouveau libres côté distant).
 *   Les blocages manuels de l'hôte (icalSync = null) et ceux d'autres flux ne sont jamais touchés.
 * - Chevauchement avec une réservation confirmée : l'import ne modifie JAMAIS une réservation.
 *   Le blocage est tout de même créé (les dates sont indisponibles de toute façon) et un
 *   avertissement est journalisé pour signaler un éventuel double-booking à arbitrer par l'hôte.
 */
final class ICalImporter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sync(PropertyICalSync $sync): SyncReport
    {
        $response = $this->httpClient->request('GET', (string) $sync->getICalUrl(), [
            'timeout' => 15,
            'max_redirects' => 3,
        ]);

        return $this->import($sync, $response->getContent());
    }

    /**
     * Applique le contenu iCal (séparé de sync() pour pouvoir être testé sans HTTP).
     */
    public function import(PropertyICalSync $sync, string $ics): SyncReport
    {
        $property = $sync->getProperty();
        $created = 0;
        $updated = 0;
        $conflicts = 0;
        $seenUids = [];

        foreach ($this->parseEvents($ics) as $event) {
            $seenUids[] = $event['uid'];
            $lastNight = $event['end']->modify('-1 day');
            if ($lastNight < $event['start']) {
                $lastNight = $event['start'];
            }

            $block = $this->availabilityRepository->findOneBy(['icalSync' => $sync, 'externalUid' => $event['uid']]);
            if ($block === null) {
                $block = new PropertyAvailability();
                $block->setProperty($property);
                $block->setIcalSync($sync);
                $block->setExternalUid($event['uid']);
                $block->setIsAvailable(false);
                $this->entityManager->persist($block);
                ++$created;
            } else {
                ++$updated;
            }

            $block->setStartDate($event['start']);
            $block->setEndDate($lastNight);
            $block->setBlockNote($this->note($sync, $event['summary']));

            if ($this->reservationRepository->findConfirmedConflict($property, $event['start'], $event['end']) !== null) {
                ++$conflicts;
                $this->logger->warning('Import iCal : blocage en conflit avec une réservation confirmée.', [
                    'property' => (string) $property?->getId(),
                    'provider' => $sync->getProviderName(),
                    'start' => $event['start']->format('Y-m-d'),
                    'end' => $event['end']->format('Y-m-d'),
                ]);
            }
        }

        // Suppression d'évènements distants : blocages importés par ce flux mais absents du flux courant.
        $removed = 0;
        foreach ($this->availabilityRepository->findBy(['icalSync' => $sync]) as $existing) {
            if (!in_array($existing->getExternalUid(), $seenUids, true)) {
                $this->entityManager->remove($existing);
                ++$removed;
            }
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new SyncReport($created, $updated, $removed, $conflicts);
    }

    /**
     * @return list<array{uid: string, start: \DateTimeImmutable, end: \DateTimeImmutable, summary: string}>
     */
    private function parseEvents(string $ics): array
    {
        // Normalisation des fins de ligne puis dépliage (RFC 5545 §3.1 : continuation = CRLF + espace/tab).
        $ics = str_replace(["\r\n", "\r"], "\n", $ics);
        $ics = preg_replace("/\n[ \t]/", '', $ics) ?? $ics;

        $events = [];
        $current = null;

        foreach (explode("\n", $ics) as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $current = [];
                continue;
            }
            if ($line === 'END:VEVENT') {
                if (is_array($current) && isset($current['start'])) {
                    $start = $current['start'];
                    $end = $current['end'] ?? $start->modify('+1 day');
                    $uid = $current['uid'] ?? ('autogen-' . $start->format('Ymd') . '-' . $end->format('Ymd'));
                    $events[] = [
                        'uid' => $uid,
                        'start' => $start,
                        'end' => $end,
                        'summary' => $current['summary'] ?? '',
                    ];
                }
                $current = null;
                continue;
            }
            if ($current === null) {
                continue;
            }

            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtoupper(explode(';', substr($line, 0, $pos))[0]);
            $value = substr($line, $pos + 1);

            match ($name) {
                'UID' => $current['uid'] = trim($value),
                'SUMMARY' => $current['summary'] = $this->unescape(trim($value)),
                'DTSTART' => $current['start'] = $this->parseDate($value) ?? ($current['start'] ?? null),
                'DTEND' => $current['end'] = $this->parseDate($value) ?? ($current['end'] ?? null),
                default => null,
            };
        }

        return $events;
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        if (preg_match('/(\d{8})/', $value, $matches) !== 1) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('!Ymd', $matches[1]) ?: null;
    }

    private function unescape(string $value): string
    {
        return str_replace(['\\,', '\\;', '\\n', '\\N', '\\\\'], [',', ';', "\n", "\n", '\\'], $value);
    }

    private function note(PropertyICalSync $sync, string $summary): string
    {
        $note = 'Importé — ' . (string) $sync->getProviderName();
        if ($summary !== '') {
            $note .= ' — ' . $summary;
        }

        return mb_substr($note, 0, 500);
    }
}
