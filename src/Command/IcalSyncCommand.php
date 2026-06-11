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
    description: 'Synchronise les calendriers iCal importés et bloque les nuitées distantes.',
)]
final class IcalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $syncs,
        private readonly ICalImporter $importer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncs = $this->syncs->findAll();

        if ($syncs === []) {
            $io->warning('Aucune source iCal configurée.');

            return Command::SUCCESS;
        }

        $hasError = false;
        foreach ($syncs as $sync) {
            $label = sprintf('%s (%s)', $sync->getProperty()?->getTitle() ?? '?', $sync->getProviderName() ?? 'ical');

            try {
                $result = $this->importer->import($sync);
                $io->writeln(sprintf(
                    '<info>%s</info> : %d évènement(s), %d bloquée(s), %d supprimée(s), %d ignorée(s) pour conflit.',
                    $label,
                    $result->events,
                    $result->created,
                    $result->removed,
                    $result->skipped,
                ));
            } catch (\Throwable $exception) {
                $hasError = true;
                $io->error(sprintf('%s : %s', $label, $exception->getMessage()));
            }
        }

        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }
}
