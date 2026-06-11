<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyICalSyncRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Importe les flux iCal externes et bloque les dates correspondantes.',
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

    protected function configure(): void
    {
        $this->addOption('property', 'p', InputOption::VALUE_OPTIONAL, 'UUID d\'une propriété spécifique');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronisation iCal');

        $syncs = $this->icalSyncRepository->findAll();

        if ($propertyId = $input->getOption('property')) {
            $syncs = array_filter($syncs, fn ($s) => (string) $s->getProperty()?->getId() === $propertyId);
        }

        if (empty($syncs)) {
            $io->warning('Aucun flux iCal à synchroniser.');
            return Command::SUCCESS;
        }

        $total = 0;

        foreach ($syncs as $sync) {
            $property = $sync->getProperty();
            $url      = $sync->getICalUrl();

            if ($property === null || $url === null) {
                continue;
            }

            $io->section(sprintf('[%s] %s', $sync->getProviderName() ?? 'externe', $property->getTitle()));

            try {
                $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
                $content  = $response->getContent();
            } catch (\Throwable $e) {
                $io->error('Erreur HTTP : '.$e->getMessage());
                continue;
            }

            $events = $this->parseVEvents($content);
            $io->text(count($events).' événement(s) trouvé(s).');

            foreach ($events as [$start, $end]) {
                $current = $start;
                // DTEND est exclusif en iCal (comme notre checkout) — on bloque start..end-1
                while ($current < $end) {
                    $existing = $this->availabilityRepository->findOneBy([
                        'property'      => $property,
                        'availableDate' => $current,
                    ]);

                    if ($existing === null) {
                        $avail = new PropertyAvailability();
                        $avail->setProperty($property);
                        $avail->setAvailableDate($current);
                        $avail->setIsAvailable(false);
                        $this->em->persist($avail);
                        $total++;
                    } elseif ($existing->isAvailable()) {
                        $existing->setIsAvailable(false);
                        $total++;
                    }

                    $current = $current->modify('+1 day');
                }
            }

            $sync->setLastSyncAt(new \DateTimeImmutable());
        }

        $this->em->flush();
        $io->success(sprintf('%d jour(s) bloqué(s) au total.', $total));

        return Command::SUCCESS;
    }

    /**
     * Retourne un tableau de [\DateTimeImmutable $start, \DateTimeImmutable $end].
     *
     * @return list<array{\DateTimeImmutable, \DateTimeImmutable}>
     */
    private function parseVEvents(string $ical): array
    {
        $events = [];
        // Déplie les lignes (RFC 5545 line folding)
        $ical = preg_replace('/\r\n[ \t]/', '', $ical) ?? $ical;

        preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/s', $ical, $matches);

        foreach ($matches[0] as $vevent) {
            $start = $this->extractDate($vevent, 'DTSTART');
            $end   = $this->extractDate($vevent, 'DTEND');

            if ($start !== null && $end !== null && $end > $start) {
                $events[] = [$start, $end];
            }
        }

        return $events;
    }

    private function extractDate(string $vevent, string $property): ?\DateTimeImmutable
    {
        // Supporte DTSTART;VALUE=DATE:20260901 et DTSTART:20260901T120000Z
        if (!preg_match('/'.$property.'[^:]*:(\d{8})(T\d{6}Z?)?/', $vevent, $m)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Ymd', $m[1], new \DateTimeZone('UTC'));

        return $date !== false ? $date->setTime(0, 0) : null;
    }
}
