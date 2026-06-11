<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PropertyAvailability;
use App\Repository\PropertyICalSyncRepository;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Importe les flux iCal externes et bloque les dates correspondantes',
)]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $icalSyncRepository,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncs = $this->icalSyncRepository->findAll();

        if (count($syncs) === 0) {
            $io->info('Aucun flux iCal à synchroniser.');

            return Command::SUCCESS;
        }

        foreach ($syncs as $sync) {
            $property = $sync->getProperty();
            if ($property === null) {
                continue;
            }

            $io->text(sprintf('Synchronisation : %s (%s)', $property->getTitle(), $sync->getProviderName()));

            try {
                $response = $this->httpClient->request('GET', $sync->getICalUrl(), ['timeout' => 10]);
                $icsContent = $response->getContent();
            } catch (\Throwable $e) {
                $io->warning(sprintf('Erreur HTTP pour %s : %s', $sync->getICalUrl(), $e->getMessage()));
                continue;
            }

            $events = $this->parseIcs($icsContent);
            $importedCount = 0;

            foreach ($events as $event) {
                if (!isset($event['DTSTART'], $event['DTEND'])) {
                    continue;
                }

                $start = $this->parseIcsDate($event['DTSTART']);
                $end   = $this->parseIcsDate($event['DTEND']);

                if ($start === null || $end === null || $start >= $end) {
                    continue;
                }

                $current = $start;
                while ($current < $end) {
                    $existing = $this->availabilityRepository->findOneByPropertyAndDate($property, $current);

                    if ($existing === null) {
                        $existing = new PropertyAvailability();
                        $existing->setProperty($property);
                        $existing->setAvailableDate($current);
                        $this->em->persist($existing);
                    }

                    $existing->setIsAvailable(false);
                    $current = $current->modify('+1 day');
                    ++$importedCount;
                }
            }

            $sync->setLastSyncAt(new \DateTimeImmutable());
            $this->em->flush();

            $io->success(sprintf('%d nuitée(s) bloquée(s) pour %s', $importedCount, $property->getTitle()));
        }

        return Command::SUCCESS;
    }

    /**
     * Minimal iCal parser: extracts VEVENT blocks as key/value arrays.
     *
     * @return array<array<string,string>>
     */
    private function parseIcs(string $content): array
    {
        // Unfold lines (RFC 5545 §3.1)
        $content = preg_replace("/\r\n[ \t]/", '', $content) ?? $content;
        $lines = preg_split('/\r\n|\n/', $content) ?: [];

        $events = [];
        $current = null;

        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $current = [];
            } elseif ($line === 'END:VEVENT' && $current !== null) {
                $events[] = $current;
                $current = null;
            } elseif ($current !== null && str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                // Strip parameters (e.g. DTSTART;VALUE=DATE → DTSTART)
                $key = explode(';', $key)[0];
                $current[$key] = $value;
            }
        }

        return $events;
    }

    private function parseIcsDate(string $value): ?\DateTimeImmutable
    {
        // DATE format: YYYYMMDD
        if (preg_match('/^\d{8}$/', $value)) {
            $dt = \DateTimeImmutable::createFromFormat('Ymd', $value);

            return $dt !== false ? $dt->setTime(0, 0) : null;
        }

        // DATETIME format: YYYYMMDDTHHmmss[Z]
        if (preg_match('/^\d{8}T\d{6}/', $value)) {
            $dt = \DateTimeImmutable::createFromFormat('Ymd\THis', substr($value, 0, 15));

            return $dt !== false ? $dt : null;
        }

        return null;
    }
}
