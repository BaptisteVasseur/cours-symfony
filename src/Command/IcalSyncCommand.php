<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyICalSyncRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Service\ICal\ICalParser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Importe les flux iCal externes configurés (PropertyICalSync) et bloque les
 * nuitées correspondantes (énoncé §Partie F). Conçue pour être automatisée
 * (Cron) et idempotente.
 *
 * Stratégie de conflits :
 *  - Suppression d'événements distants : à chaque exécution, on PURGE d'abord
 *    les blocages de cette source ('ical:{provider}') sur la fenêtre, puis on
 *    réinsère ceux du flux courant. Un événement disparu côté distant est donc
 *    automatiquement débloqué.
 *  - Chevauchement avec une réservation « confirmed » locale : on N'altère PAS
 *    la réservation (priorité au local) et on n'ajoute pas de blocage redondant ;
 *    le conflit est journalisé.
 *  - Les blocages manuels de l'hôte (source 'host') ne sont jamais touchés.
 */
#[AsCommand(
    name: 'app:ical:sync',
    description: 'Importe les calendriers iCal externes et bloque les nuitées occupées.',
)]
final class IcalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository     $syncRepository,
        private readonly PropertyRepository             $propertyRepository,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository          $reservationRepository,
        private readonly HttpClientInterface            $httpClient,
        private readonly ICalParser                     $parser,
        private readonly EntityManagerInterface         $entityManager,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('property', null, InputOption::VALUE_REQUIRED, 'Limiter la synchronisation à un logement (UUID)')
            ->addOption('window', null, InputOption::VALUE_REQUIRED, 'Fenêtre à synchroniser, en jours', '365');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $syncs = $this->resolveSyncs($input, $io);
        if ($syncs === null) {
            return Command::FAILURE;
        }
        if ($syncs === []) {
            $io->warning('Aucun flux iCal à synchroniser.');

            return Command::SUCCESS;
        }

        $windowDays = max(1, (int)$input->getOption('window'));
        $from = new DateTimeImmutable('today');
        $to = $from->modify(sprintf('+%d days', $windowDays));

        foreach ($syncs as $sync) {
            $this->synchronize($sync, $from, $to, $io);
        }

        return Command::SUCCESS;
    }

    private function synchronize(PropertyICalSync $sync, DateTimeImmutable $from, DateTimeImmutable $to, SymfonyStyle $io): void
    {
        $property = $sync->getProperty();
        if ($property === null) {
            return;
        }
        $source = 'ical:' . $sync->getProviderName();

        try {
            $content = $this->httpClient->request('GET', (string)$sync->getICalUrl())->getContent();
        } catch (Throwable $e) {
            $io->warning(sprintf('%s — téléchargement impossible : %s', $property->getTitle(), $e->getMessage()));

            return;
        }

        $ranges = $this->parser->parse($content);

        // Purge des blocages précédents de cette source dans la fenêtre (gère la
        // suppression d'événements distants). DELETE DQL immédiat.
        $this->availabilityRepository->clearBlocksForSource($property, $source, $from, $to);

        $blocked = 0;
        $conflicts = 0;
        foreach ($ranges as $range) {
            $day = max($range['start'], $from);
            $end = min($range['end'], $to->modify('+1 day'));

            while ($day < $end) {
                if ($this->reservationRepository->hasOverlap($property, $day, $day->modify('+1 day'), ['confirmed'])) {
                    // Jour déjà couvert par une réservation confirmée : conflit
                    // journalisé, pas de blocage redondant, réservation préservée.
                    ++$conflicts;
                    $day = $day->modify('+1 day');
                    continue;
                }

                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($day);
                $availability->setIsAvailable(false);
                $availability->setSource($source);
                $this->entityManager->persist($availability);
                ++$blocked;

                $day = $day->modify('+1 day');
            }
        }

        $sync->setLastSyncAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $io->success(sprintf(
            '%s (%s) : %d nuitée(s) bloquée(s), %d conflit(s) avec des réservations confirmées.',
            $property->getTitle(),
            $sync->getProviderName(),
            $blocked,
            $conflicts,
        ));
    }

    /**
     * @return list<PropertyICalSync>|null null si l'UUID fourni est introuvable
     */
    private function resolveSyncs(InputInterface $input, SymfonyStyle $io): ?array
    {
        $propertyId = $input->getOption('property');
        if ($propertyId === null) {
            return $this->syncRepository->findAll();
        }

        if (!Uuid::isValid((string)$propertyId)) {
            $io->error('UUID de logement invalide.');

            return null;
        }

        $property = $this->propertyRepository->find(Uuid::fromString((string)$propertyId));
        if ($property === null) {
            $io->error('Logement introuvable.');

            return null;
        }

        return $this->syncRepository->findBy(['property' => $property]);
    }
}
