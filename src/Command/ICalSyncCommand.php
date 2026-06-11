<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\ICalImportMessage;
use App\Repository\PropertyICalSyncRepository;
use App\Repository\PropertyRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Synchronise les calendriers iCal importés depuis des plateformes externes.',
)]
class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $syncRepo,
        private readonly PropertyRepository $propertyRepo,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('property-id', null, InputOption::VALUE_OPTIONAL, 'UUID du logement à synchroniser (tous si absent)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $propertyId = $input->getOption('property-id');

        if ($propertyId !== null) {
            $property = $this->propertyRepo->find($propertyId);
            if ($property === null) {
                $io->error(sprintf('Logement "%s" introuvable.', $propertyId));

                return Command::FAILURE;
            }

            $syncs = $this->syncRepo->findBy(['property' => $property]);
        } else {
            $syncs = $this->syncRepo->findAll();
        }

        if (empty($syncs)) {
            $io->info('Aucun calendrier iCal à synchroniser.');

            return Command::SUCCESS;
        }

        $io->progressStart(count($syncs));

        foreach ($syncs as $sync) {
            $this->bus->dispatch(new ICalImportMessage((string) $sync->getId()));
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf('%d message(s) iCal envoyés à la queue.', count($syncs)));

        return Command::SUCCESS;
    }
}
