<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PropertyICalSyncRepository;
use App\Service\ICalImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Synchronises all external iCal feeds and blocks corresponding nights.',
)]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $iCalSyncRepository,
        private readonly ICalImportService $iCalImportService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('property-id', null, InputOption::VALUE_OPTIONAL, 'Only sync a specific property UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $propertyId = $input->getOption('property-id');

        $syncs = $propertyId
            ? $this->iCalSyncRepository->findByPropertyId($propertyId)
            : $this->iCalSyncRepository->findAll();

        if (empty($syncs)) {
            $io->info('No iCal sync entries found.');
            return Command::SUCCESS;
        }

        $totalBlocked = 0;
        $totalConflicts = 0;

        foreach ($syncs as $sync) {
            $propertyTitle = $sync->getProperty()?->getTitle() ?? 'Unknown';
            $io->text(sprintf('Syncing "%s" (%s)...', $propertyTitle, $sync->getProviderName()));

            try {
                $stats = $this->iCalImportService->syncForProperty($sync);
                $totalBlocked += $stats['blocked'];
                $totalConflicts += $stats['conflicts'];
                $io->text(sprintf(
                    '  → %d days blocked, %d skipped, %d conflicts',
                    $stats['blocked'],
                    $stats['skipped'],
                    $stats['conflicts'],
                ));
            } catch (\Throwable $e) {
                $io->warning(sprintf('  → Failed: %s', $e->getMessage()));
            }
        }

        $io->success(sprintf(
            'Sync complete. Total: %d days blocked, %d conflicts.',
            $totalBlocked,
            $totalConflicts,
        ));

        return Command::SUCCESS;
    }
}
