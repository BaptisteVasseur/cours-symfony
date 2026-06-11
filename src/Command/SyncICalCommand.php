<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PropertyUnavailability;
use App\Repository\PropertyICalSyncRepository;
use App\Repository\PropertyUnavailabilityRepository;
use App\Service\ICalImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Importe les calendriers iCal externes et bloque les dates correspondantes',
)]
final class SyncICalCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $iCalSyncRepository,
        private readonly PropertyUnavailabilityRepository $unavailabilityRepository,
        private readonly ICalImportService $iCalImportService,
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'property',
            null,
            InputOption::VALUE_REQUIRED,
            'UUID du logement à synchroniser (tous si omis)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $propertyId = $input->getOption('property');
        $syncs = $this->iCalSyncRepository->findAllWithProperty(is_string($propertyId) ? $propertyId : null);
        $importedCount = 0;

        foreach ($syncs as $sync) {
            $property = $sync->getProperty();
            $providerName = $sync->getProviderName();
            $iCalUrl = $sync->getICalUrl();

            if ($property === null || $providerName === null || $iCalUrl === null) {
                continue;
            }

            try {
                $response = $this->httpClient->request('GET', $iCalUrl);
                $content = $response->getContent();
            } catch (\Throwable $e) {
                $io->warning(sprintf('Échec sync %s : %s', $providerName, $e->getMessage()));
                continue;
            }

            $ranges = $this->iCalImportService->parse($content);
            $this->unavailabilityRepository->deleteBySource($property, $providerName);

            foreach ($ranges as $range) {
                $unavailability = new PropertyUnavailability();
                $unavailability->setProperty($property);
                $unavailability->setStartDate($range['start']);
                $unavailability->setEndDate($range['end']);
                $unavailability->setReason($providerName);
                $unavailability->setSource($providerName);
                $this->entityManager->persist($unavailability);
                ++$importedCount;
            }

            $sync->setLastSyncAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d période(s) importée(s) depuis %d source(s) iCal.',
            $importedCount,
            count($syncs),
        ));

        return Command::SUCCESS;
    }
}
