<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PropertyBlock;
use App\Repository\PropertyBlockRepository;
use App\Repository\PropertyICalSyncRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Synchronise les blocages depuis les flux iCal externes de chaque logement.',
)]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $syncRepository,
        private readonly PropertyBlockRepository $blockRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncs = $this->syncRepository->findAll();

        if ($syncs === []) {
            $io->info('Aucun flux iCal configuré.');

            return Command::SUCCESS;
        }

        foreach ($syncs as $sync) {
            $property = $sync->getProperty();
            $url      = $sync->getICalUrl();

            $io->section(sprintf('[%s] %s', $sync->getProviderName(), $property?->getTitle() ?? '?'));

            try {
                $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
                $content  = $response->getContent();
            } catch (\Throwable $e) {
                $io->warning(sprintf('Impossible de récupérer %s : %s', $url, $e->getMessage()));
                continue;
            }

            $events = $this->parseIcal($content);

            // Supprimer les anciens blocs issus de ce flux (par iCalUid présent)
            $existingBlocks = $this->blockRepository->findByICalSource($property, $sync->getProviderName());
            foreach ($existingBlocks as $old) {
                $this->em->remove($old);
            }
            $this->em->flush();

            $created = 0;
            $skipped = 0;

            foreach ($events as $event) {
                if (
                    !isset($event['DTSTART'], $event['DTEND'], $event['UID'])
                    || $event['DTSTART'] >= $event['DTEND']
                ) {
                    continue;
                }

                // Vérifier que l'événement ne chevauche pas une réservation confirmée
                $overlap = $this->reservationRepository->countOverlapping($property, $event['DTSTART'], $event['DTEND']);
                if ($overlap > 0) {
                    ++$skipped;
                    $io->comment(sprintf('  Conflit ignoré : %s (%s → %s)', $event['UID'], $event['DTSTART']->format('Y-m-d'), $event['DTEND']->format('Y-m-d')));
                    continue;
                }

                $block = new PropertyBlock();
                $block->setProperty($property);
                $block->setDateStart($event['DTSTART']);
                $block->setDateEnd($event['DTEND']);
                $block->setReason(($event['SUMMARY'] ?? $sync->getProviderName()).' (iCal)');
                $block->setICalUid($sync->getProviderName().':'.$event['UID']);
                $this->em->persist($block);
                ++$created;
            }

            $this->em->flush();

            $sync->setLastSyncAt(new \DateTimeImmutable());
            $this->em->flush();

            $io->success(sprintf('%d bloq(s) créé(s), %d ignoré(s).', $created, $skipped));
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<array{DTSTART: \DateTimeImmutable, DTEND: \DateTimeImmutable, UID: string, SUMMARY?: string}>
     */
    private function parseIcal(string $content): array
    {
        // Unfold long lines (RFC 5545 §3.1)
        $content = preg_replace("/\r\n[ \t]/", '', $content) ?? $content;
        $content = preg_replace("/\n[ \t]/", '', $content) ?? $content;

        $events  = [];
        $current = null;

        foreach (explode("\n", $content) as $rawLine) {
            $line = rtrim($rawLine, "\r");

            if ($line === 'BEGIN:VEVENT') {
                $current = [];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($current !== null) {
                    $events[] = $current;
                }
                $current = null;
                continue;
            }

            if ($current === null) {
                continue;
            }

            [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
            $baseKey = explode(';', $key)[0];

            if ($baseKey === 'DTSTART' || $baseKey === 'DTEND') {
                $date = $this->parseIcalDate($value);
                if ($date !== null) {
                    $current[$baseKey] = $date;
                }
            } elseif ($baseKey === 'UID') {
                $current['UID'] = $value;
            } elseif ($baseKey === 'SUMMARY') {
                $current['SUMMARY'] = $value;
            }
        }

        return $events;
    }

    private function parseIcalDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);

        // DATE-TIME: 20251225T140000Z or 20251225T140000
        if (preg_match('/^(\d{8})T(\d{6})(Z?)$/', $value, $m)) {
            $tz   = $m[3] === 'Z' ? 'UTC' : 'Europe/Paris';
            $date = \DateTimeImmutable::createFromFormat('Ymd\\THis', $m[1].'T'.$m[2], new \DateTimeZone($tz));

            return $date !== false ? $date->setTimezone(new \DateTimeZone('Europe/Paris')) : null;
        }

        // DATE: 20251225
        if (preg_match('/^\d{8}$/', $value)) {
            $date = \DateTimeImmutable::createFromFormat('Ymd', $value, new \DateTimeZone('Europe/Paris'));

            return $date !== false ? $date->setTime(0, 0) : null;
        }

        return null;
    }
}
