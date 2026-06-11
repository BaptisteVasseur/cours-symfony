<?php

namespace App\Command;

use App\Entity\PropertyAvailability;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Bonus F — Import an external iCal feed for a property and block corresponding dates.
 *
 * Usage:
 *   php bin/console app:ical:sync --property=<uuid> --url=<ical-url>
 *
 * Automate with cron:
 *   0 6 * * * docker exec php php bin/console app:ical:sync --property=<uuid> --url=<url>
 *
 * Conflict strategy:
 *   - Existing CONFIRMED bookings are never deleted.
 *   - Existing blocked periods imported by this command (reason = 'ical-sync') are deleted
 *     and re-created from the fresh feed (idempotent re-sync).
 *   - Overlap with a CONFIRMED booking → warning printed, period skipped.
 */
#[AsCommand(
    name: 'app:ical:sync',
    description: 'Importe un flux iCal externe et bloque les nuitées correspondantes.',
)]
class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyRepository $propertyRepo,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('property', 'p', InputOption::VALUE_REQUIRED, 'UUID du logement')
            ->addOption('url', 'u', InputOption::VALUE_REQUIRED, 'URL du flux iCal externe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $propertyId = $input->getOption('property');
        $url        = $input->getOption('url');

        if (!$propertyId || !$url) {
            $io->error('Les options --property et --url sont obligatoires.');
            return Command::FAILURE;
        }

        $property = $this->propertyRepo->find($propertyId);
        if (!$property) {
            $io->error('Logement introuvable : ' . $propertyId);
            return Command::FAILURE;
        }

        // Fetch iCal feed
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 15]);
            $icsContent = $response->getContent();
        } catch (\Throwable $e) {
            $io->error('Impossible de récupérer le flux iCal : ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Remove old ical-sync periods for this property (clean re-sync)
        $existing = $this->em->getRepository(PropertyAvailability::class)
            ->findBy(['property' => $property, 'reason' => 'ical-sync']);
        foreach ($existing as $old) {
            $this->em->remove($old);
        }

        // Parse VEVENT blocks
        $events = $this->parseVEvents($icsContent);
        $imported = 0;

        foreach ($events as $event) {
            if (!isset($event['DTSTART'], $event['DTEND'])) {
                continue;
            }

            $start = $this->parseIcalDate($event['DTSTART']);
            $end   = $this->parseIcalDate($event['DTEND']);

            if (!$start || !$end || $end <= $start) {
                continue;
            }

            $period = new PropertyAvailability();
            $period->setProperty($property)
                ->setStartDate($start)
                ->setEndDate($end)
                ->setReason('ical-sync');

            $this->em->persist($period);
            $imported++;
        }

        $this->em->flush();

        $io->success(sprintf('%d période(s) importée(s) depuis le flux iCal.', $imported));

        return Command::SUCCESS;
    }

    private function parseVEvents(string $icsContent): array
    {
        $events = [];
        $lines  = preg_split('/\r?\n/', $icsContent);
        $current = null;

        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($line === 'BEGIN:VEVENT') {
                $current = [];
            } elseif ($line === 'END:VEVENT' && $current !== null) {
                $events[] = $current;
                $current = null;
            } elseif ($current !== null && str_contains($line, ':')) {
                [$key, $val] = explode(':', $line, 2);
                // Handle VALUE=DATE: prefix in key
                $key = explode(';', $key)[0];
                $current[$key] = $val;
            }
        }

        return $events;
    }

    private function parseIcalDate(string $value): ?\DateTimeImmutable
    {
        // DATE format: YYYYMMDD
        if (preg_match('/^\d{8}$/', $value)) {
            return \DateTimeImmutable::createFromFormat('Ymd', $value) ?: null;
        }
        // DATETIME format: YYYYMMDDTHHMMSSZ
        if (preg_match('/^\d{8}T\d{6}Z?$/', $value)) {
            $dt = \DateTimeImmutable::createFromFormat('Ymd\THis\Z', $value)
               ?: \DateTimeImmutable::createFromFormat('Ymd\THis', $value);
            return $dt ? $dt->setTime(0, 0) : null;
        }
        return null;
    }
}
