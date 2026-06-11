<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
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
    description: 'Importe les calendriers iCal externes et bloque les dates correspondantes.',
)]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('property-id', null, InputOption::VALUE_OPTIONAL, 'Synchroniser uniquement ce logement (UUID)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $propertyId = $input->getOption('property-id');

        $qb = $this->entityManager->createQueryBuilder()
            ->select('s', 'p')
            ->from('App\Entity\PropertyICalSync', 's')
            ->leftJoin('s.property', 'p');

        if ($propertyId !== null) {
            $qb->andWhere('p.id = :id')->setParameter('id', $propertyId);
        }

        $syncs = $qb->getQuery()->getResult();

        if (empty($syncs)) {
            $io->info('Aucune source iCal à synchroniser.');
            return Command::SUCCESS;
        }

        foreach ($syncs as $sync) {
            $property = $sync->getProperty();
            $url = $sync->getICalUrl();
            $io->text(sprintf('Synchronisation de "%s" depuis %s', $property->getTitle(), $url));

            try {
                // Appel HTTP Client vers la ressource externe
                $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
                $icsContent = $response->getContent();
            } catch (\Throwable $e) {
                $io->warning(sprintf('Erreur HTTP pour %s : %s', $url, $e->getMessage()));
                continue;
            }

            $events = $this->parseIcal($icsContent);
            $blocked = 0;

            foreach ($events as ['start' => $start, 'end' => $end]) {
                $cursor = $start;
                // On bloque chaque jour de l'événement (le dernier jour = checkout, non bloqué)
                while ($cursor < $end) {
                    $existing = $this->availabilityRepository->findOneByPropertyAndDate($property, $cursor);

                    if ($existing === null) {
                        $pa = new PropertyAvailability();
                        $pa->setProperty($property);
                        $pa->setAvailableDate($cursor);
                        $pa->setIsAvailable(false);
                        $this->entityManager->persist($pa);
                        $blocked++;
                    } elseif ($existing->isAvailable()) {
                        $existing->setIsAvailable(false);
                        $blocked++;
                    }

                    $cursor = $cursor->modify('+1 day');
                }
            }

            $sync->setLastSyncAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $io->text(sprintf('  → %d jour(s) bloqué(s)', $blocked));
        }

        $io->success('Synchronisation iCal terminée.');
        return Command::SUCCESS;
    }

    /**
     * Parse basique d'un flux iCal — extrait les DTSTART/DTEND de chaque VEVENT.
     *
     * @return list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    private function parseIcal(string $content): array
    {
        // Normalise les fins de ligne
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        // Déplie les lignes continuées (ligne commençant par un espace)
        $content = preg_replace('/\n[ \t]/', '', $content) ?? $content;

        $events = [];
        $inEvent = false;
        $start = null;
        $end = null;

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $start = null;
                $end = null;
                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($start !== null && $end !== null) {
                    $events[] = ['start' => $start, 'end' => $end];
                }
                $inEvent = false;
                continue;
            }

            if (!$inEvent) {
                continue;
            }

            // DTSTART;VALUE=DATE:20260710 ou DTSTART:20260710T150000Z
            if (str_starts_with($line, 'DTSTART')) {
                $value = substr($line, strpos($line, ':') + 1);
                $start = $this->parseIcalDate($value);
            } elseif (str_starts_with($line, 'DTEND')) {
                $value = substr($line, strpos($line, ':') + 1);
                $end = $this->parseIcalDate($value);
            }
        }

        return $events;
    }

    private function parseIcalDate(string $value): ?\DateTimeImmutable
    {
        // Format DATE : 20260710
        if (preg_match('/^(\d{8})$/', $value, $m)) {
            return \DateTimeImmutable::createFromFormat('Ymd', $m[1]) ?: null;
        }

        // Format DATETIME : 20260710T150000Z ou 20260710T150000
        if (preg_match('/^(\d{8}T\d{6})/', $value, $m)) {
            $dt = \DateTimeImmutable::createFromFormat('Ymd\THis', $m[1]);
            return $dt ?: null;
        }

        return null;
    }
}
