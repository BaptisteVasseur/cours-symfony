<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PropertyICalSync;
use App\Repository\PropertyICalSyncRepository;
use App\Service\IcalImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Synchronise les calendriers iCal externes des logements.',
)]
final class IcalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $iCalSyncRepository,
        private readonly IcalImportService $iCalImportService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('syncId', InputArgument::OPTIONAL, 'UUID du flux iCal a synchroniser.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncs = $this->getSyncs($input->getArgument('syncId'));

        if ($syncs === []) {
            $io->warning('Aucun flux iCal a synchroniser.');

            return Command::SUCCESS;
        }

        $hasError = false;

        foreach ($syncs as $sync) {
            $propertyTitle = $sync->getProperty()?->getTitle() ?? 'Logement inconnu';
            $io->section(sprintf('%s - %s', $propertyTitle, $sync->getProviderName()));

            try {
                $result = $this->iCalImportService->sync($sync);
                $io->success(sprintf(
                    '%d cree, %d mis a jour, %d supprime, %d conflit, %d ignore.',
                    $result['created'],
                    $result['updated'],
                    $result['removed'],
                    $result['conflicts'],
                    $result['skipped'],
                ));
            } catch (\Throwable $exception) {
                $hasError = true;
                $io->error($exception->getMessage());
            }
        }

        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return list<PropertyICalSync>
     */
    private function getSyncs(mixed $syncId): array
    {
        if (!is_string($syncId) || $syncId === '') {
            return $this->iCalSyncRepository->findAll();
        }

        if (!Uuid::isValid($syncId)) {
            throw new \InvalidArgumentException('UUID de flux iCal invalide.');
        }

        $sync = $this->iCalSyncRepository->find(Uuid::fromString($syncId));

        return $sync instanceof PropertyICalSync ? [$sync] : [];
    }
}
