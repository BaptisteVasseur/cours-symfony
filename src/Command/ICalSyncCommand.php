<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyICalSyncRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Importe les disponibilités depuis les flux iCal externes (PropertyICalSync)',
)]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $syncRepository,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule la synchronisation sans écrire en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Mode dry-run : aucune écriture en base.');
        }

        /** @var PropertyICalSync[] $syncs */
        $syncs = $this->syncRepository->findAll();

        if (empty($syncs)) {
            $io->info('Aucun flux iCal configuré.');

            return Command::SUCCESS;
        }

        $io->progressStart(count($syncs));

        $totalBlocked = 0;
        $totalSkipped = 0;

        foreach ($syncs as $sync) {
            $property     = $sync->getProperty();
            $providerName = $sync->getProviderName() ?? 'unknown';
            $url          = $sync->getICalUrl();

            if ($property === null || $url === null) {
                $io->progressAdvance();
                continue;
            }

            try {
                $icsContent = $this->fetchUrl($url);
            } catch (\RuntimeException $e) {
                $io->warning(sprintf('[%s] Impossible de récupérer le flux : %s', $providerName, $e->getMessage()));
                $io->progressAdvance();
                continue;
            }

            $ranges = $this->parseIcs($icsContent);

            // Chargement des blocs existants pour cette propriété (indexed by date string)
            $existing = $this->availabilityRepository->findByProperty($property);
            $existingMap = [];
            foreach ($existing as $avail) {
                $existingMap[$avail->getAvailableDate()->format('Y-m-d')] = $avail;
            }

            $blockedCount = 0;
            $skippedCount = 0;

            foreach ($ranges as [$checkin, $checkout]) {
                $cursor = $checkin;
                // DTEND en iCal est exclusif (jour de départ non bloqué)
                while ($cursor < $checkout) {
                    $dateKey = $cursor->format('Y-m-d');

                    if (isset($existingMap[$dateKey])) {
                        // Conflit : on ne touche pas aux blocs manuels
                        $skippedCount++;
                    } else {
                        $blockedCount++;
                        if (!$dryRun) {
                            $avail = new PropertyAvailability();
                            $avail->setProperty($property);
                            $avail->setAvailableDate($cursor);
                            $avail->setIsAvailable(false);
                            $avail->setReason('ical:' . $providerName);
                            $this->em->persist($avail);
                        }
                        $existingMap[$dateKey] = true; // évite les doublons intra-flux
                    }

                    $cursor = $cursor->modify('+1 day');
                }
            }

            if (!$dryRun) {
                $sync->setLastSyncAt(new \DateTimeImmutable());
                $this->em->flush();
            }

            $totalBlocked += $blockedCount;
            $totalSkipped += $skippedCount;

            $io->progressAdvance();
        }

        $io->progressFinish();

        $io->success(sprintf(
            '%d jour(s) bloqué(s), %d conflit(s) ignoré(s) (blocs manuels conservés).',
            $totalBlocked,
            $totalSkipped,
        ));

        return Command::SUCCESS;
    }

    /**
     * Récupère le contenu d'une URL (HTTP/HTTPS ou fichier local pour les tests).
     */
    private function fetchUrl(string $url): string
    {
        $context = stream_context_create(['http' => ['timeout' => 10]]);
        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new \RuntimeException('Échec du téléchargement : ' . $url);
        }

        return $content;
    }

    /**
     * Parse un fichier iCal et retourne une liste de [DateTimeImmutable $start, DateTimeImmutable $end].
     *
     * @return list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
     */
    private function parseIcs(string $content): array
    {
        // Dépliage des lignes (RFC 5545 : continuation = CRLF + espace/tab)
        $content = preg_replace("/\r\n[ \t]/", '', $content) ?? $content;
        $content = preg_replace("/\n[ \t]/", '', $content) ?? $content;

        $ranges  = [];
        $inEvent = false;
        $dtstart = null;
        $dtend   = null;

        foreach (explode("\n", str_replace("\r\n", "\n", $content)) as $raw) {
            $line = rtrim($raw);

            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $dtstart = null;
                $dtend   = null;
                continue;
            }

            if ($line === 'END:VEVENT') {
                $inEvent = false;
                if ($dtstart !== null && $dtend !== null && $dtstart < $dtend) {
                    $ranges[] = [$dtstart, $dtend];
                }
                continue;
            }

            if (!$inEvent) {
                continue;
            }

            if (str_starts_with($line, 'DTSTART')) {
                $dtstart = $this->parseIcsDate($line);
            } elseif (str_starts_with($line, 'DTEND')) {
                $dtend = $this->parseIcsDate($line);
            } elseif (str_starts_with($line, 'DURATION') && $dtstart !== null) {
                // Support DURATION à la place de DTEND (RFC 5545 §3.8.2.5)
                $duration = $this->parseDuration(substr($line, strpos($line, ':') + 1));
                if ($duration !== null) {
                    $dtend = $dtstart->modify($duration);
                }
            }
        }

        return $ranges;
    }

    private function parseIcsDate(string $line): ?\DateTimeImmutable
    {
        // Exemples :
        //   DTSTART;VALUE=DATE:20260101
        //   DTSTART:20260101T120000Z
        //   DTSTART;TZID=Europe/Paris:20260101T140000
        $value = substr($line, strpos($line, ':') + 1);

        if (preg_match('/^(\d{8})$/', $value)) {
            // Date seule → minuit UTC
            $dt = \DateTimeImmutable::createFromFormat('Ymd', $value, new \DateTimeZone('UTC'));

            return $dt !== false ? $dt->setTime(0, 0) : null;
        }

        if (preg_match('/^(\d{8}T\d{6}Z)$/', $value)) {
            $dt = \DateTimeImmutable::createFromFormat('Ymd\THis\Z', $value, new \DateTimeZone('UTC'));

            return $dt !== false ? $dt : null;
        }

        if (preg_match('/^(\d{8}T\d{6})$/', $value)) {
            $dt = \DateTimeImmutable::createFromFormat('Ymd\THis', $value, new \DateTimeZone('UTC'));

            return $dt !== false ? $dt : null;
        }

        return null;
    }

    /**
     * Convertit une durée iCal (ex: P1D, P2W) en modificateur PHP (ex: "+1 day").
     */
    private function parseDuration(string $duration): ?string
    {
        if (preg_match('/^P(\d+)W$/', $duration, $m)) {
            return '+' . ((int) $m[1] * 7) . ' days';
        }
        if (preg_match('/^P(\d+)D$/', $duration, $m)) {
            return '+' . $m[1] . ' days';
        }

        return null;
    }
}
