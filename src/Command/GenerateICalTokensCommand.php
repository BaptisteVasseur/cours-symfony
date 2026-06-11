<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-ical-tokens',
    description: 'Generate iCal tokens for properties and hosts that don\'t have one',
)]
final class GenerateICalTokensCommand extends Command
{
    public function __construct(
        private PropertyRepository $propertyRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $properties = $this->propertyRepository->findAll();
        $propertyCount = 0;
        $hostCount = 0;
        $processedHostIds = [];

        foreach ($properties as $property) {
            if (!$property->getIcalToken()) {
                $property->generateIcalToken();
                $this->entityManager->persist($property);
                $propertyCount++;
            }

            $host = $property->getHost();
            if ($host && !isset($processedHostIds[$host->getId()])) {
                $processedHostIds[$host->getId()] = true;

                if (!$host->getHostIcalToken()) {
                    $host->generateHostIcalToken();
                    $this->entityManager->persist($host);
                    $hostCount++;
                }
            }
        }

        if ($propertyCount > 0 || $hostCount > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('Generated %d property token(s) and %d host token(s).', $propertyCount, $hostCount));
        } else {
            $io->info('All properties and hosts already have iCal tokens.');
        }

        return Command::SUCCESS;
    }
}
