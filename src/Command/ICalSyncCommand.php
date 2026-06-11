<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PropertyICalSyncRepository;
use App\Service\ICalImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Import iCal feeds from PropertyICalSync records and block corresponding dates',
)]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $syncRepository,
        private readonly ICalImportService $importService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('iCal Sync');

        $syncs = $this->syncRepository->findAll();

        if (count($syncs) === 0) {
            $io->info('Aucun flux iCal configuré.');
            return Command::SUCCESS;
        }

        $totalBlocked = 0;
        $errors = 0;

        foreach ($syncs as $sync) {
            $property = $sync->getProperty();
            $io->write(sprintf(
                '  Syncing "%s" (%s)… ',
                $property?->getTitle() ?? '?',
                $sync->getProviderName() ?? $sync->getICalUrl(),
            ));

            try {
                $count = $this->importService->sync($sync);
                $totalBlocked += $count;
                $io->writeln(sprintf('<info>%d nuits bloquées</info>', $count));
            } catch (\Throwable $e) {
                $errors++;
                $io->writeln(sprintf('<error>Erreur : %s</error>', $e->getMessage()));
            }
        }

        $io->success(sprintf(
            'Synchronisation terminée : %d nuits bloquées au total (%d erreur(s)).',
            $totalBlocked,
            $errors,
        ));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
