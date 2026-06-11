<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\BookingRepository;
use App\Service\BookingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:bookings:expire-pending',
    description: 'Annule automatiquement les demandes en attente trop anciennes (>24h).',
)]
final class ExpirePendingBookingsCommand extends Command
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly BookingService $bookingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('hours', null, InputOption::VALUE_REQUIRED, 'Délai d\'expiration en heures', '24');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = max(1, (int) $input->getOption('hours'));
        $threshold = (new \DateTimeImmutable())->modify("-{$hours} hours");

        $stale = $this->bookingRepository->findPendingOlderThan($threshold);

        if ($stale === []) {
            $io->success('Aucune demande à expirer.');

            return Command::SUCCESS;
        }

        foreach ($stale as $booking) {
            $this->bookingService->expireAsSystem($booking);
            $io->writeln(sprintf(' - Réservation %s expirée (créée le %s).',
                $booking->getId(),
                $booking->getCreatedAt()?->format('d/m/Y H:i'),
            ));
        }

        $io->success(sprintf('%d demande(s) expirée(s).', count($stale)));

        return Command::SUCCESS;
    }
}
