<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PropertyICalSyncRepository;
use App\Service\ICal\ICalImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Synchronise les calendriers iCal externes configurés sur les logements.',
)]
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
            $io->info('Aucune source iCal à synchroniser.');

            return Command::SUCCESS;
        }

        $hasError = false;
        foreach ($syncs as $sync) {
            $label = sprintf('%s (%s)', $sync->getProviderName(), (string) $sync->getProperty()?->getId());
            try {
                $count = $this->importer->import($sync);
                $io->writeln(sprintf('<info>OK</info> %s : %d évènement(s)', $label, $count));
            } catch (\Throwable $exception) {
                $hasError = true;
                $io->writeln(sprintf('<error>KO</error> %s : %s', $label, $exception->getMessage()));
            }
        }

        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }
}
