<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\PropertyICalSyncException;
use App\Repository\PropertyICalSyncRepository;
use App\Repository\PropertyRepository;
use App\Service\PropertyICalSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:property:ical:sync', description: 'Synchronise les calendriers iCal externes des logements.')]
final class SyncPropertyICalCommand extends Command
{
    public function __construct(
        private PropertyICalSyncRepository $propertyICalSyncRepository,
        private PropertyRepository $propertyRepository,
        private PropertyICalSyncService $propertyICalSyncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('property', null, InputOption::VALUE_REQUIRED, 'UUID du logement a synchroniser')
            ->addOption('sync', null, InputOption::VALUE_REQUIRED, 'UUID de la source iCal a synchroniser')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncId = $input->getOption('sync');
        $propertyId = $input->getOption('property');

        try {
            if (is_string($syncId) && $syncId !== '') {
                $sync = $this->propertyICalSyncRepository->find($syncId);

                if ($sync === null) {
                    $io->error('Source iCal introuvable.');

                    return Command::FAILURE;
                }

                $count = $this->propertyICalSyncService->sync($sync);
                $io->success(sprintf('Synchronisation terminee pour "%s" (%d jours bloques).', $sync->getProviderName(), $count));

                return Command::SUCCESS;
            }

            if (is_string($propertyId) && $propertyId !== '') {
                $property = $this->propertyRepository->find($propertyId);

                if ($property === null) {
                    $io->error('Logement introuvable.');

                    return Command::FAILURE;
                }

                $syncs = $this->propertyICalSyncRepository->findByPropertyId($propertyId);
            } else {
                $syncs = $this->propertyICalSyncRepository->findAll();
            }

            if ($syncs === []) {
                $io->warning('Aucune source iCal a synchroniser.');

                return Command::SUCCESS;
            }

            foreach ($syncs as $sync) {
                $count = $this->propertyICalSyncService->sync($sync);
                $io->writeln(sprintf('%s : %d jours bloques', $sync->getProviderName(), $count));
            }

            $io->success('Synchronisation iCal terminee.');
        } catch (PropertyICalSyncException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
