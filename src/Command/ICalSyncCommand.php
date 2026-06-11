<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AvailabilityBlock;
use App\Entity\Listing;
use App\Repository\AvailabilityBlockRepository;
use App\Repository\BookingRepository;
use App\Repository\ListingRepository;
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
    description: 'Synchronise les calendriers iCal externes des logements (import des indisponibilités).',
)]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly ListingRepository $listingRepository,
        private readonly AvailabilityBlockRepository $blockRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly ICalImportService $importService,
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('listing', null, InputOption::VALUE_REQUIRED, 'UUID d\'un logement précis (sinon tous)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $listings = $this->resolveListings($input->getOption('listing'));
        if ($listings === []) {
            $io->warning('Aucun logement avec une URL iCal à synchroniser.');

            return Command::SUCCESS;
        }

        $totalCreated = $totalRemoved = $totalConflicts = 0;

        foreach ($listings as $listing) {
            $io->section($listing->getTitle() ?? (string) $listing->getId());

            try {
                $ics = $this->httpClient->request('GET', $listing->getIcalImportUrl())->getContent();
            } catch (\Throwable $e) {
                $io->error('Téléchargement échoué : ' . $e->getMessage());
                continue;
            }

            $events = $this->importService->parse($ics);
            $existing = $this->blockRepository->findImportedIndexedByUid($listing);
            $seenUids = [];

            foreach ($events as $i => $event) {
                $uid = $event->uid ?? sprintf('no-uid-%s-%d', $event->start->format('Ymd'), $i);
                $seenUids[$uid] = true;

                if ($this->bookingRepository->hasConfirmedOverlap($listing, $event->start, $event->end)) {
                    $io->warning(sprintf('Conflit : l\'événement %s chevauche une réservation confirmée locale.', $uid));
                    ++$totalConflicts;
                }

                $block = $existing[$uid] ?? (new AvailabilityBlock())
                    ->setListing($listing)
                    ->setSource(AvailabilityBlock::SOURCE_ICAL)
                    ->setExternalUid($uid);

                $block->setStartDate($event->start)
                    ->setEndDate($event->end)
                    ->setReason($event->summary ?? 'Importé (iCal)');

                if (!isset($existing[$uid])) {
                    $this->em->persist($block);
                    ++$totalCreated;
                }
            }

            foreach ($existing as $uid => $block) {
                if (!isset($seenUids[$uid])) {
                    $this->em->remove($block);
                    ++$totalRemoved;
                }
            }

            $this->em->flush();
            $io->writeln(sprintf('%d événement(s) traité(s).', count($events)));
        }

        $io->success(sprintf(
            'Synchronisation terminée : %d créé(s), %d supprimé(s), %d conflit(s) signalé(s).',
            $totalCreated,
            $totalRemoved,
            $totalConflicts,
        ));

        return Command::SUCCESS;
    }

    private function resolveListings(?string $listingId): array
    {
        if ($listingId !== null) {
            $listing = $this->listingRepository->find($listingId);

            return ($listing !== null && $listing->getIcalImportUrl() !== null) ? [$listing] : [];
        }

        return $this->listingRepository->createQueryBuilder('l')
            ->andWhere('l.icalImportUrl IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}
