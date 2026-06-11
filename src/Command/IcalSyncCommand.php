<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PropertyICalSyncRepository;
use App\Service\ICalImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Importe les calendriers iCal externes et bloque les nuitées correspondantes.',
)]
class IcalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $syncs,
        private readonly ICalImporter $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('property', InputArgument::OPTIONAL, 'UUID d\'un logement pour ne synchroniser que celui-ci');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $propertyId = $input->getArgument('property');

        $syncs = $this->syncs->findAllToSync($propertyId);
        if ($syncs === []) {
            $io->warning('Aucune source iCal à synchroniser.');

            return Command::SUCCESS;
        }

        $rows = [];
        $errors = 0;

        foreach ($syncs as $sync) {
            $label = sprintf('%s (%s)', $sync->getProperty()?->getTitle() ?? '?', $sync->getProviderName() ?? 'externe');

            try {
                $result = $this->importer->sync($sync);
                $rows[] = [$label, $result['events'], $result['blocked'], $result['unblocked'], $result['conflicts'], 'OK'];
            } catch (\Throwable $exception) {
                ++$errors;
                $rows[] = [$label, '-', '-', '-', '-', 'ERREUR : ' . $exception->getMessage()];
            }
        }

        $io->table(['Logement', 'Événements', 'Bloquées', 'Libérées', 'Conflits', 'Statut'], $rows);

        if ($errors > 0) {
            $io->warning(sprintf('%d source(s) en erreur.', $errors));

            return Command::FAILURE;
        }

        $io->success('Synchronisation iCal terminée.');

        return Command::SUCCESS;
    }
}
