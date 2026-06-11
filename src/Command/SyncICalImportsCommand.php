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
    description: 'Synchronise les flux iCal externes et bloque les nuits importees',
)]
final class SyncICalImportsCommand extends Command
{
    public function __construct(
        private PropertyICalSyncRepository $propertyICalSyncRepository,
        private ICalImportService $iCalImportService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncs = $this->propertyICalSyncRepository->findAllWithProperty();

        if ($syncs === []) {
            $io->warning('Aucune source iCal a synchroniser.');

            return Command::SUCCESS;
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($syncs as $sync) {
            $property = $sync->getProperty();
            $propertyLabel = $property ? $property->getTitle() : 'N/A';

            try {
                $stats = $this->iCalImportService->sync($sync);
                $successCount++;

                $io->writeln(sprintf(
                    '[OK] %s (%s) -> ranges:%d days:%d created:%d updated:%d skipped:%d',
                    $sync->getProviderName() ?? 'Provider',
                    $propertyLabel,
                    $stats['ranges'],
                    $stats['days'],
                    $stats['created'],
                    $stats['updated'],
                    $stats['skipped']
                ));
            } catch (\Throwable $throwable) {
                $errorCount++;
                $io->error(sprintf(
                    '%s (%s): %s',
                    $sync->getProviderName() ?? 'Provider',
                    $propertyLabel,
                    $throwable->getMessage()
                ));
            }
        }

        if ($errorCount > 0) {
            $io->warning(sprintf('Synchronisation terminee: %d succes, %d erreur(s).', $successCount, $errorCount));

            return Command::FAILURE;
        }

        $io->success(sprintf('Synchronisation terminee: %d source(s) traitee(s).', $successCount));

        return Command::SUCCESS;
    }
}

