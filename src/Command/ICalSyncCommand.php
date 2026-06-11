<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
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
    description: 'Importe les flux iCal externes et bloque les nuitées correspondantes.',
)]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $iCalSyncRepository,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncs = $this->iCalSyncRepository->findAll();

        if (empty($syncs)) {
            $io->info('Aucun flux iCal configuré.');

            return Command::SUCCESS;
        }

        foreach ($syncs as $sync) {
            $property = $sync->getProperty();
            if ($property === null) {
                continue;
            }

            $io->text(sprintf('Synchronisation de "%s" depuis %s…', $property->getTitle(), $sync->getProviderName()));

            try {
                $response = $this->httpClient->request('GET', $sync->getICalUrl(), ['timeout' => 10]);
                $icsContent = $response->getContent();
            } catch (\Throwable $e) {
                $io->warning(sprintf('Impossible de récupérer le flux : %s', $e->getMessage()));
                continue;
            }

            $events = $this->parseICalEvents($icsContent);
            $imported = 0;

            foreach ($events as $event) {
                $checkin = $event['dtstart'];
                $checkout = $event['dtend'];

                if ($checkin === null || $checkout === null) {
                    continue;
                }

                // Stratégie conflits : on ne bloque pas les dates déjà couvertes par une réservation confirmed
                if ($this->reservationRepository->hasOverlappingConfirmed($property, $checkin, $checkout)) {
                    $io->warning(sprintf('Conflit ignoré : réservation confirmée couvre déjà %s – %s', $checkin->format('Y-m-d'), $checkout->format('Y-m-d')));
                    continue;
                }

                // Bloquer chaque jour de la plage [checkin, checkout[
                $cursor = $checkin;
                while ($cursor < $checkout) {
                    $existing = $this->availabilityRepository->findOneBy([
                        'property' => $property,
                        'availableDate' => $cursor,
                    ]);

                    if ($existing === null) {
                        $avail = new PropertyAvailability();
                        $avail->setProperty($property);
                        $avail->setAvailableDate($cursor);
                        $avail->setIsAvailable(false);
                        $this->entityManager->persist($avail);
                        ++$imported;
                    } elseif ($existing->isAvailable()) {
                        $existing->setIsAvailable(false);
                        ++$imported;
                    }

                    $cursor = $cursor->modify('+1 day');
                }
            }

            $sync->setLastSyncAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $io->success(sprintf('%d jour(s) bloqué(s) pour "%s".', $imported, $property->getTitle()));
        }

        return Command::SUCCESS;
    }

    /**
     * Parse minimaliste iCal : extrait les VEVENT avec DTSTART et DTEND.
     *
     * @return list<array{dtstart: \DateTimeImmutable|null, dtend: \DateTimeImmutable|null}>
     */
    private function parseICalEvents(string $content): array
    {
        $events = [];
        $inEvent = false;
        $current = ['dtstart' => null, 'dtend' => null];

        foreach (explode("\n", $content) as $rawLine) {
            $line = rtrim($rawLine, "\r");

            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $current = ['dtstart' => null, 'dtend' => null];
                continue;
            }

            if ($line === 'END:VEVENT') {
                $inEvent = false;
                $events[] = $current;
                continue;
            }

            if (!$inEvent) {
                continue;
            }

            if (str_starts_with($line, 'DTSTART')) {
                $current['dtstart'] = $this->parseICalDate($line);
            } elseif (str_starts_with($line, 'DTEND')) {
                $current['dtend'] = $this->parseICalDate($line);
            }
        }

        return $events;
    }

    private function parseICalDate(string $line): ?\DateTimeImmutable
    {
        // Supporte DTSTART;VALUE=DATE:20260710 et DTSTART:20260710T150000Z
        $value = substr($line, strpos($line, ':') + 1);
        $value = trim($value);

        // Date seule YYYYMMDD
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {
            return new \DateTimeImmutable(sprintf('%s-%s-%s', $m[1], $m[2], $m[3]));
        }

        // DateTime YYYYMMDDTHHMMSSZ
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T/', $value, $m)) {
            return new \DateTimeImmutable(sprintf('%s-%s-%s', $m[1], $m[2], $m[3]));
        }

        return null;
    }
}
