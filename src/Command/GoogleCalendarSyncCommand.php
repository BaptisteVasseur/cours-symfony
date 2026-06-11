<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\GoogleCalendarSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:google-calendar:sync', description: 'Importe les événements Google Calendar comme indisponibilités')]
final class GoogleCalendarSyncCommand extends Command
{
    public function __construct(
        private GoogleCalendarSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronisation Google Calendar');

        $results = $this->syncService->syncAll();

        if (empty($results)) {
            $io->info('Aucun calendrier connecté à synchroniser.');
            return Command::SUCCESS;
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($results as $propertyId => $result) {
            if ($result['status'] === 'success') {
                $io->success(sprintf('Propriété %s : %d jour(s) bloqué(s)', $propertyId, $result['blocked']));
                ++$successCount;
            } else {
                $io->error(sprintf('Propriété %s : %s', $propertyId, $result['message']));
                ++$errorCount;
            }
        }

        $io->section(sprintf('Terminé : %d succès, %d erreurs', $successCount, $errorCount));

        return Command::SUCCESS;
    }
}
