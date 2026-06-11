<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyICalSyncRepository;
use App\Repository\ReservationRepository;
use App\Service\ICalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:ical:sync', description: 'Synchronise les calendriers iCal externes et bloque les dates occupées.')]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $iCalSyncRepository,
        private readonly ICalService $iCalService,
        private readonly PropertyAvailabilityRepository $availabilityRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule la synchronisation sans modifier la base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $syncs = $this->iCalSyncRepository->findAll();

        if (empty($syncs)) {
            $io->info('Aucune source iCal configurée.');

            return Command::SUCCESS;
        }

        foreach ($syncs as $sync) {
            $property = $sync->getProperty();
            $source = $sync->getProviderName();
            $io->section(\sprintf('[%s] Synchronisation : %s', $property->getTitle(), $source));

            try {
                $events = $this->iCalService->import($sync->getICalUrl());
            } catch (\Throwable $e) {
                $io->error('Erreur lors de la récupération du flux : '.$e->getMessage());
                continue;
            }

            $io->text(\sprintf('%d événement(s) trouvé(s).', count($events)));

            if (!$dryRun) {
                // Suppression des anciens blocages iCal pour cette source avant re-synchronisation.
                // Les blocages manuels de l'hôte (icalSyncSource = null) sont préservés.
                $deleted = $this->availabilityRepository->deleteFutureBySource($property, $source);
                if ($deleted > 0) {
                    $io->text(\sprintf('  %d blocage(s) iCal précédent(s) supprimé(s).', $deleted));
                }
            }

            foreach ($events as $event) {
                $from = $event['dtstart'];
                $to = $event['dtend'];

                // Détection de chevauchement avec une réservation CONFIRMED existante.
                $conflicts = $this->reservationRepository->countConfirmedConflicts($property, $from, $to);
                if ($conflicts > 0) {
                    $io->warning(\sprintf(
                        '  Conflit : %s → %s chevauche %d réservation(s) confirmée(s). Dates bloquées quand même.',
                        $from->format('Y-m-d'),
                        $to->format('Y-m-d'),
                        $conflicts,
                    ));
                }

                if (!$dryRun) {
                    $this->blockRangeFromIcal($property, $from, $to, $source);
                }

                $io->text(\sprintf('  Bloqué : %s → %s (%s)', $from->format('Y-m-d'), $to->format('Y-m-d'), $event['summary']));
            }

            if (!$dryRun) {
                $sync->setLastSyncAt(new \DateTimeImmutable());
                $this->entityManager->flush();
            }

            $io->success(\sprintf('Synchronisation "%s" terminée.', $source));
        }

        return Command::SUCCESS;
    }

    private function blockRangeFromIcal(
        Property $property,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        string $source,
    ): void {
        $current = $from;
        while ($current < $to) {
            $existing = $this->availabilityRepository->findByPropertyAndDate($property, $current);

            if ($existing === null) {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($current);
                $availability->setIsAvailable(false);
                $availability->setIcalSyncSource($source);
                $this->entityManager->persist($availability);
            } elseif ($existing->isAvailable()) {
                $existing->setIsAvailable(false);
                $existing->setIcalSyncSource($source);
            }

            $current = $current->modify('+1 day');
        }
    }
}
