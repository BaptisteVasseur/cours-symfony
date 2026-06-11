<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ReservationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reservations:complete',
    description: 'Marque comme terminés les séjours confirmés dont la date de départ est passée.',
)]
class ReservationsCompleteCommand extends Command
{
    public function __construct(
        private readonly ReservationManager $reservationManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->reservationManager->completeEndedStays(new \DateTimeImmutable('today'));

        if ($count === 0) {
            $io->success('Aucun séjour à clôturer.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('%d séjour(s) marqué(s) comme terminé(s).', $count));

        return Command::SUCCESS;
    }
}
