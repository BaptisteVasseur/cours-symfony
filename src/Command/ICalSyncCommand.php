<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use App\Service\ICalImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:ical:sync', description: 'Synchronise les indisponibilites depuis les flux iCal externes.')]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyRepository $propertyRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly ICalImportService $iCalImportService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('property-id', null, InputOption::VALUE_REQUIRED, 'Identifiant du logement a synchroniser.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $properties = $this->resolveProperties($input);
        if ($properties === []) {
            $output->writeln('<comment>Aucun logement a synchroniser.</comment>');

            return Command::SUCCESS;
        }

        $hasFailure = false;

        foreach ($properties as $property) {
            $url = $property->getExternalIcalUrl();
            if ($url === null || $url === '') {
                $output->writeln(sprintf('<comment>%s : aucune URL iCal configuree.</comment>', $property->getTitle()));
                continue;
            }

            try {
                $content = $this->httpClient->request('GET', $url, ['timeout' => 10])->getContent();
                $stats = $this->iCalImportService->parseAndSync($property, $content);
                $output->writeln(sprintf(
                    '<info>%s</info> : %d cree(s), %d modifie(s), %d supprime(s), %d ignore(s).',
                    $property->getTitle(),
                    $stats['created'],
                    $stats['updated'],
                    $stats['deleted'],
                    $stats['skipped'],
                ));
            } catch (\Throwable $exception) {
                $hasFailure = true;
                $output->writeln(sprintf('<error>%s : %s</error>', $property->getTitle(), $exception->getMessage()));
            }
        }

        return $hasFailure ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return list<Property>
     */
    private function resolveProperties(InputInterface $input): array
    {
        $propertyId = $input->getOption('property-id');
        if (is_string($propertyId) && $propertyId !== '') {
            $property = $this->propertyRepository->find($propertyId);

            return $property instanceof Property ? [$property] : [];
        }

        return $this->propertyRepository->findWithExternalIcalUrl();
    }
}
