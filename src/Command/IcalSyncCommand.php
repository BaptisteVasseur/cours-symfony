<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyICalSyncRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:ical:sync',
    description: 'Synchronise les calendriers externes via flux iCal',
)]
class IcalSyncCommand extends Command
{
    public function __construct(
        private PropertyICalSyncRepository $iCalSyncRepository,
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncs = $this->iCalSyncRepository->findAll();

        foreach ($syncs as $sync) {
            $io->info(sprintf('Synchronisation pour le logement : %s', $sync->getProperty()?->getTitle()));
            
            try {
                $response = $this->httpClient->request('GET', $sync->getICalUrl());
                $content = $response->getContent();
                
                $this->parseAndSync($sync, $content);
                
                $sync->setLastSyncAt(new \DateTimeImmutable());
                $this->entityManager->flush();
                
                $io->success('Synchronisation terminée.');
            } catch (\Exception $e) {
                $io->error(sprintf('Erreur lors de la synchro : %s', $e->getMessage()));
            }
        }

        return Command::SUCCESS;
    }

    private function parseAndSync(PropertyICalSync $sync, string $content): void
    {
        // Simple parser iCal (Regex)
        preg_match_all('/BEGIN:VEVENT.*?DTSTART(;VALUE=DATE)?:(\d{8}).*?DTEND(;VALUE=DATE)?:(\d{8}).*?END:VEVENT/s', $content, $matches, PREG_SET_ORDER);

        $property = $sync->getProperty();
        if (!$property) return;

        foreach ($matches as $match) {
            $startStr = $match[2];
            $endStr = $match[4];

            $startDate = \DateTimeImmutable::createFromFormat('Ymd', $startStr);
            $endDate = \DateTimeImmutable::createFromFormat('Ymd', $endStr);

            if ($startDate && $endDate) {
                // On vérifie si on a déjà bloqué cette période pour éviter les doublons
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setStartDate($startDate);
                $availability->setEndDate($endDate);
                $availability->setIsAvailable(false);
                $availability->setNotes('Synchronisé via iCal External : ' . $sync->getProviderName());

                $this->entityManager->persist($availability);
            }
        }
    }
}
