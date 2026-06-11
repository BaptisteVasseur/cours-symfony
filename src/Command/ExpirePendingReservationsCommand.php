<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ReservationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reservations:expire-pending',
    description: 'Cancel pending reservations that have not been processed within the allowed window (default: 24h)',
)]
final class ExpirePendingReservationsCommand extends Command
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'hours',
            null,
            InputOption::VALUE_OPTIONAL,
            'Expiry threshold in hours',
            '24',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $hours = max(1, (int) $input->getOption('hours'));
        $threshold = new \DateInterval(sprintf('PT%dH', $hours));

        $io->text(sprintf('Cancelling pending reservations older than %dh…', $hours));

        $count = $this->reservationService->expirePending($threshold);

        if ($count > 0) {
            $io->success(sprintf('%d réservation(s) en attente expirée(s) et annulée(s).', $count));
        } else {
            $io->info('Aucune réservation à expirer.');
        }

        return Command::SUCCESS;
    }
}
