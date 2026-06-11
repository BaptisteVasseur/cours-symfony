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

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Importe les calendriers iCal externes et bloque les nuits correspondantes (à automatiser via Cron).',
)]
class ICalSyncCommand extends Command
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
            $io->warning('Aucun flux iCal à synchroniser.');

            return Command::SUCCESS;
        }

        $total = 0;
        foreach ($syncs as $sync) {
            $label = ($sync->getProperty()?->getTitle() ?? '?') . ' (' . $sync->getProviderName() . ')';

            try {
                $imported = $this->importer->import($sync);
                $total += $imported;
                $io->writeln(sprintf('<info>OK</info>  %s : %d évènement(s) bloqué(s)', $label, $imported));
            } catch (\Throwable $e) {
                $io->writeln(sprintf('<error>KO</error>  %s : %s', $label, $e->getMessage()));
            }
        }

        $io->success(sprintf('Synchronisation terminée : %d évènement(s) importé(s) au total.', $total));

        return Command::SUCCESS;
    }
}