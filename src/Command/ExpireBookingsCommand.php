<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\BookingRepository;
use App\Service\BookingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:booking:expire', description: 'Annule les demandes de reservation pending expirees apres 24h.')]
final class ExpireBookingsCommand extends Command
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly BookingService $bookingService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $threshold = new \DateTimeImmutable('-24 hours');
        $reservations = $this->bookingRepository->findExpiredPending($threshold);

        if ($reservations === []) {
            $io->success('Aucune demande de reservation expiree.');

            return Command::SUCCESS;
        }

        $expiredCount = 0;
        $hasFailure = false;

        foreach ($reservations as $reservation) {
            try {
                $this->bookingService->expire($reservation);
                ++$expiredCount;
            } catch (\Throwable $exception) {
                $hasFailure = true;
                $io->error(sprintf(
                    'Reservation %s : %s',
                    $reservation->getId()?->toRfc4122() ?? 'inconnue',
                    $exception->getMessage(),
                ));
            }
        }

        $io->success(sprintf('%d demande(s) de reservation expiree(s).', $expiredCount));

        return $hasFailure ? Command::FAILURE : Command::SUCCESS;
    }
}
