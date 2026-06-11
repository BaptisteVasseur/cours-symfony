<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:bookings:expire', description: 'Annule les demandes pending non traitées après 24h.')]
final class ExpirePendingBookingsCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationService $reservationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $expired = $this->reservationRepository->findExpiredPending();

        if (empty($expired)) {
            $io->info('Aucune réservation expirée.');

            return Command::SUCCESS;
        }

        foreach ($expired as $reservation) {
            $this->reservationService->expire($reservation);
        }

        $io->success(\sprintf('%d réservation(s) expirée(s) annulée(s).', count($expired)));

        return Command::SUCCESS;
    }
}
