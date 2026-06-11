<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PropertyICalSyncRepository;
use App\Service\ICalImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;

#[AsCommand(name: 'app:ical:sync', description: 'Synchronise les calendriers iCal externes et bloque les nuitées correspondantes.')]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $syncs,
        private readonly ICalImportService $importer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncs = $this->syncs->findAll();

        if ($syncs === []) {
            $io->info('Aucun calendrier iCal à synchroniser.');

            return Command::SUCCESS;
        }

        $total = 0;
        foreach ($syncs as $sync) {
            try {
                $blocked = $this->importer->sync($sync);
                $total += $blocked;
                $io->writeln(sprintf('%s : %d nuitée(s) bloquée(s)', $sync->getProviderName(), $blocked));
            } catch (\Throwable $exception) {
                $io->warning(sprintf('%s : échec (%s)', $sync->getProviderName(), $exception->getMessage()));
            }
        }

        $io->success(sprintf('Synchronisation terminée : %d nuitée(s) bloquée(s).', $total));

        return Command::SUCCESS;
    }
}
