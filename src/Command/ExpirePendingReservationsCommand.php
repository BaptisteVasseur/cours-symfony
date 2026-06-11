<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ReservationRepository;
use App\Service\ReservationWorkflowService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reservation:expire-pending',
    description: 'Annule automatiquement les demandes pending expirées',
)]
final class ExpirePendingReservationsCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationWorkflowService $workflowService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $expired = $this->reservationRepository->findExpiredPending();
        $count = 0;

        foreach ($expired as $reservation) {
            try {
                $this->workflowService->expire($reservation);
                ++$count;
            } catch (\Throwable) {
                continue;
            }
        }

        $io->success(sprintf('%d demande(s) expirée(s) traitée(s).', $count));

        return Command::SUCCESS;
    }
}
