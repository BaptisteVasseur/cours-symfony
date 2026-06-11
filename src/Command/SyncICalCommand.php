<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PropertyICalSyncRepository;
use App\Service\ICal\ICalSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:ical:sync', description: 'Synchronise les flux iCal externes des logements')]
final class SyncICalCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $syncRepository,
        private readonly ICalSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncs = $this->syncRepository->findAll();
        $successCount = 0;
        $errorCount = 0;
        $blockedCount = 0;

        foreach ($syncs as $sync) {
            try {
                $blocked = $this->syncService->sync($sync);
                $blockedCount += $blocked;
                ++$successCount;
                $io->writeln(sprintf('%s: %d nuit(s) bloquee(s)', $sync->getProviderName(), $blocked));
            } catch (\Throwable $exception) {
                ++$errorCount;
                $io->error(sprintf('%s: %s', $sync->getProviderName() ?? 'Flux iCal', $exception->getMessage()));
            }
        }

        if ($successCount === 0 && $errorCount === 0) {
            $io->success('Aucun flux iCal a synchroniser.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('%d flux synchronise(s), %d erreur(s), %d nuit(s) bloquee(s).', $successCount, $errorCount, $blockedCount));

        return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
