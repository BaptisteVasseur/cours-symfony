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
    name: 'app:reservation:complete-past',
    description: 'Passe en completed les réservations confirmées dont la date de départ est passée',
)]
final class CompletePastReservationsCommand extends Command
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
        $count = 0;

        foreach ($this->reservationRepository->findConfirmedPastCheckout() as $reservation) {
            $host = $reservation->getProperty()?->getHost();
            if ($host === null) {
                continue;
            }

            try {
                $this->workflowService->complete($reservation, $host);
                ++$count;
            } catch (\Throwable) {
                continue;
            }
        }

        $io->success(sprintf('%d réservation(s) marquée(s) comme terminée(s).', $count));

        return Command::SUCCESS;
    }
}
