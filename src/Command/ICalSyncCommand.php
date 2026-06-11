<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PropertyICalSyncRepository;
use App\Service\ICalImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Synchronise les calendriers iCal externes et bloque les nuitées correspondantes.',
)]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $syncs,
        private readonly ICalImporter $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('property', null, InputOption::VALUE_REQUIRED, 'Limiter la synchronisation à un logement (UUID).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $propertyId = $input->getOption('property');

        if (is_string($propertyId) && !Uuid::isValid($propertyId)) {
            $io->error(sprintf('Identifiant de logement invalide : "%s". Attendu : un UUID.', $propertyId));

            return Command::INVALID;
        }

        $syncs = $this->syncs->findForSync(is_string($propertyId) ? $propertyId : null);

        if ($syncs === []) {
            $io->warning('Aucun flux iCal à synchroniser.');

            return Command::SUCCESS;
        }

        $hasError = false;

        foreach ($syncs as $sync) {
            $label = sprintf('%s (%s)', $sync->getProviderName(), $sync->getProperty()?->getTitle() ?? '?');

            try {
                $report = $this->importer->import($sync);
                $io->writeln(sprintf(
                    '<info>OK</info> %s — %d événement(s) importé(s), %d supprimé(s), %d conflit(s).',
                    $label,
                    $report['imported'],
                    $report['removed'],
                    count($report['conflicts']),
                ));

                foreach ($report['conflicts'] as $conflict) {
                    $io->writeln('  <comment>'.$conflict.'</comment>');
                }
            } catch (\Throwable $exception) {
                $hasError = true;
                $io->writeln(sprintf('<error>ÉCHEC</error> %s — %s', $label, $exception->getMessage()));
            }
        }

        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }
}
