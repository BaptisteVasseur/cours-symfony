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
    description: 'Generate iCal tokens for properties that don\'t have one',
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
        $count = 0;

        foreach ($properties as $property) {
            if (!$property->getIcalToken()) {
                $property->generateIcalToken();
                $this->entityManager->persist($property);
                $count++;
            }
        }

        if ($count > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('Generated iCal tokens for %d properties.', $count));
        } else {
            $io->info('All properties already have iCal tokens.');
        }

        return Command::SUCCESS;
    }
}
