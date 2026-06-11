<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PropertyICalSyncRepository;
use App\Service\ICalImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Synchronise les calendriers iCal importés : récupère chaque flux distant et bloque
 * les nuitées correspondantes. Conçue pour être automatisée (cron) :
 *
 *   * /15 * * * *  php bin/console app:ical:sync
 */
#[AsCommand(name: 'app:ical:sync', description: 'Importe les flux iCal des logements et bloque les nuitées distantes')]
final class IcalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $syncRepository,
        private readonly ICalImporter $importer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncs = $this->syncRepository->findAll();

        if ($syncs === []) {
            $io->info('Aucun flux iCal à synchroniser.');

            return Command::SUCCESS;
        }

        $rows = [];
        $failures = 0;

        foreach ($syncs as $sync) {
            $label = sprintf('%s (%s)', $sync->getProviderName(), $sync->getProperty()?->getTitle() ?? '?');

            try {
                $report = $this->importer->sync($sync);
                $rows[] = [$label, $report->created, $report->updated, $report->removed, $report->conflicts, 'OK'];
            } catch (\Throwable $e) {
                ++$failures;
                $rows[] = [$label, '-', '-', '-', '-', 'ERREUR: ' . $e->getMessage()];
            }
        }

        $io->table(['Flux', 'Créés', 'MàJ', 'Supprimés', 'Conflits', 'Statut'], $rows);

        if ($failures > 0) {
            $io->warning(sprintf('%d flux en erreur (voir ci-dessus).', $failures));

            return Command::FAILURE;
        }

        $io->success('Synchronisation iCal terminée.');

        return Command::SUCCESS;
    }
}
