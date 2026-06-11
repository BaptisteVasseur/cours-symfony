<?php

namespace App\Command;

use App\Repository\BookingRepository;
use App\Service\BookingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:booking:expire',
    description: 'Cancels pending booking requests older than 24h',
)]
class ExpirePendingBookingsCommand extends Command
{
    public function __construct(
        private readonly BookingRepository $bookingRepo,
        private readonly BookingService    $bookingService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $threshold = new \DateTimeImmutable('-24 hours');
        $expired   = $this->bookingRepo->findExpiredPending($threshold);

        if (count($expired) === 0) {
            $io->info('No expired pending bookings found.');
            return Command::SUCCESS;
        }

        foreach ($expired as $booking) {
            $this->bookingService->cancel($booking, 'Demande non traitée dans les 24h.', 'system');
            $io->writeln(sprintf('Expired booking #%d (listing: %s)', $booking->getId(), $booking->getListing()->getTitle()));
        }

        $io->success(sprintf('%d booking(s) expired and cancelled.', count($expired)));

        return Command::SUCCESS;
    }
}
