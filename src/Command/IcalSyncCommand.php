<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PropertyICalSyncRepository;
use App\Service\IcalImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Synchronise les calendriers iCal externes des logements',
)]
final class IcalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $iCalSyncRepository,
        private readonly IcalImportService $icalImportService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncs = $this->iCalSyncRepository->findAll();
        $totalImported = 0;
        $totalSkipped = 0;
        $totalRemoved = 0;

        foreach ($syncs as $sync) {
            try {
                $result = $this->icalImportService->sync($sync);
                $totalImported += $result['imported'];
                $totalSkipped += $result['skipped'];
                $totalRemoved += $result['removed'];
                $io->writeln(sprintf(
                    'Sync %s : %d importé(s), %d ignoré(s), %d supprimé(s)',
                    $sync->getId(),
                    $result['imported'],
                    $result['skipped'],
                    $result['removed'],
                ));
            } catch (\Throwable $e) {
                $io->warning(sprintf('Échec sync %s : %s', $sync->getId(), $e->getMessage()));
            }
        }

        $io->success(sprintf(
            'Synchronisation terminée : %d importé(s), %d ignoré(s), %d supprimé(s).',
            $totalImported,
            $totalSkipped,
            $totalRemoved,
        ));

        return Command::SUCCESS;
    }
}
